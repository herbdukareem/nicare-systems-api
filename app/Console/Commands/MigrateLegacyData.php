<?php

namespace App\Console\Commands;

use App\Enums\RelationshipToPrincipal;
use App\Enums\Settlement;
use App\Enums\Status;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Lga;
use App\Models\Ward;
use App\Models\Facility;
use App\Models\Enrollee;
use App\Models\FundingType;
use App\Models\Benefactor;
use App\Models\Premium;
use App\Models\Role;
use App\Models\User;
use App\Models\Bank;
use App\Models\AccountDetail;
use App\Models\BenefitPackage;
use App\Models\EnrollmentPhase;
use App\Models\Invoice;
use App\Models\Mda;
use App\Models\PaymentCategory;
use App\Models\PremiumType;
use App\Models\Sector;
use App\Models\Staff;
use App\Models\VulnerableGroup;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MigrateLegacyData extends Command
{
    protected $signature = 'legacy:migrate';
    protected $description = 'Migrate data from the legacy MySQL database into the new structure';

    protected $lga_array = [];
    protected $ward_array = [];
    protected $facility_array = [];
    protected $enrollee_array = [];
    protected $fund_array = [];
    protected $benefactor_array = [];
    protected $benefactor_by_name_array = [];
    protected $premium_array = [];
    protected $pin_array = [];
    protected $enrollee_type_array = [];
    protected $gacCounterpart = null;
    protected $premium_type_array = [];
    protected $user_array = [];
    protected $superadmin = null;
    protected $funding_array = [];
    protected $vulnerable_groups_arr = [];
    protected $enrollment_phase_array = [];
    protected $mda_array = [];
    protected $paymentInvoice_array = [];
    protected $standardPackage = null;
    protected $BMPHSPackage = null;


    public function handle()
    {
        
        DB::transaction(function () {
            // 1. Migrate LGAs
            $legacyLgas = DB::connection('legacy')->table('lga')->get();
            foreach ($legacyLgas as $lgaRow) {
                $newLga = Lga::updateOrCreate(
                    ['id' => $lgaRow->id],
                    [
                        'name'           => $lgaRow->lga,
                        'code'           => $lgaRow->code,
                        'zone'           => $lgaRow->zone,
                    ]
                );

                $this->lga_array[$lgaRow->id] = $newLga->id;
            }
            $this->info('LGAs migrated.');

            // 2. Migrate Wards
            $legacyWards = DB::connection('legacy')->table('ward')->get();
            foreach ($legacyWards as $wardRow) {
                $newWard =Ward::updateOrCreate(
                    ['name' => $wardRow->ward, 'lga_id' => $this->lga_array[$wardRow->lga_id]],
                    [
                        'settlement_type' => Settlement::from($wardRow->settlement ?? 'Rural')->value,
                        'status'          => 1,
                    ]
                );

                $this->ward_array[$wardRow->id] = $newWard->id;
            }
            $this->info('Wards migrated.');

            // 3. Migrate funding types
            $legacyFundingTypes = DB::connection('legacy')->table('funidng_types')->get();
            foreach ($legacyFundingTypes as $ft) {
                $newFundingType = FundingType::updateOrCreate(
                    ['name' => $ft->fuding_type],
                    [
                        'code'  => $ft->code,
                        'description' => $ft->code2,
                        'status' => 1,
                    ]
                );

                $this->fund_array[$ft->id] = $newFundingType->id;
                $this->funding_array[$ft->code2] = $newFundingType->id;
            }
            $this->info('Funding types migrated.');

            // 4. Migrate benefactors
            $legacyBenefactors = DB::connection('legacy')->table('benefactors')->get();
            foreach ($legacyBenefactors as $ben) {
                $newBenefactor = Benefactor::updateOrCreate(
                    ['name' => $ben->name],
                    [
                        'status'        => 1,
                    ]
                );
                
                $this->benefactor_array[$ben->id] = $newBenefactor->id;
                $this->benefactor_by_name_array[strtolower($ben->name)] = $newBenefactor->id;
            }

            //Create GAC Counterpart
            $this->gacCounterpart = Benefactor::updateOrCreate(
                ['name' => 'GAC Counterpart'],
                [
                    'status'  => 1
                ]
            );
            $this->info('Benefactors migrated.');

           // 5. Migrate providers into facilities
                $legacyProviders = DB::connection('legacy')->table('tbl_providers')->get();
                foreach ($legacyProviders as $provider) {
                    // Create or update facility

                    $hcpcategory = match ($provider->hcpcategory) {
                        'Public' => 1,
                        'Private' => 2,
                        'Tertiary' => 3,
                        default => 1,
                    };

                    $hcptype = match ($provider->hcptype) {
                        'Primary' => 1,
                        'Secondary' => 2,
                        'Tertiary' => 3,
                        default => 1,
                    };
                    $facility = Facility::updateOrCreate(
                        ['hcp_code' => $provider->hcpcode, 'name'  => $provider->hcpname],
                        [
                            'ownership' => $hcpcategory,
                            'type'     => $hcptype,
                            'lga_id'   => $this->lga_array[$provider->hcplga],
                            'ward_id'  => $this->ward_array[$provider->hcpward],
                            'capacity' => $provider->hcpcap ?? 1000,
                            'phone'    => $provider->hcpcontactphone ?? "",
                            'email'    => $provider->hcpemailaddress ?? "",
                        ]
                    );

                    // Create or get the bank record
                    if ($provider->hcpBankName) {
                        $bank = Bank::firstOrCreate(
                            ['name' => $provider->hcpBankName],
                            [
                                'code'      => $provider->sortCode ?: \Illuminate\Support\Str::random(5),
                                'sort_code' => $provider->sortCode,
                            ]
                        );

                        // Create the account detail with a bank_id
                        $account = AccountDetail::create([
                            'account_name'     => $provider->hcpBankAccountName,
                            'account_number'   => $provider->hcpBankAccountNumber,
                            'bank_id'          => $bank->id,
                            'account_type'     => 'savings',
                            'accountable_id'   => $facility->id,
                            'accountable_type' => Facility::class,
                        ]);

                        // If your Facility model has an account_detail_id column, update it
                        $facility->account_detail_id = $account->id;
                        $facility->save();
                    }

                    $this->facility_array[$provider->id] = $facility->id;
                }

            $this->info('Facilities migrated.');

            // create sector
            $this->createEnrolleeTypes();

            $this->standardPackage = BenefitPackage::where('code', 'standard')->first();
            $this->BMPHSPackage = BenefitPackage::where('code', 'BMPHS')->first();


              // 6. Create System Admin role and migrate users with user_role_id=1
            $role = Role::firstOrCreate([
                'name' => 'System Admin',
            ], [
                'description' => 'System Administrator',
            ]);

            // Create Supper Admin User
            // Staff Profile
            $staff = Staff::updateOrCreate(
                ['email' => 'admin@nicare.com'],
                [
                    'first_name'      => 'System',
                    'last_name'       => 'Admin',
                    'middle_name'     => '',
                    'date_of_birth'   => null,
                    'gender'          => 'Male',
                    'email'           => 'admin@nicare.com',
                    'phone'           => '08130051228',
                    'designation_id'  => null,
                    'department_id'   => null,
                    'address'         => null
                ]
            );

            
            // User Profile
            // Create or update the super admin user and associate it with the staff profile
                $superadmin = User::updateOrCreate(
                    ['email' => 'admin@nicare.com'], // use email as unique key
                    [
                        'name'          => 'System Admin',
                        'phone'         => '08130051228',
                        'username'      => 'admin',
                        'password'      => Hash::make('password'), // change to a secure value in production
                        'userable_id'   => $staff->id,
                        'userable_type' => Staff::class,
                    ]
                );

                $this->superadmin = $superadmin;

           // Assign the System Admin role
            $superadmin->roles()->syncWithoutDetaching([$role->id]);


            // After creating the System Admin role and the superadmin profile…

                $legacyUsers = DB::connection('legacy')
                    ->table('users')
                    ->where('user_role_id', 1)
                    ->get();

                foreach ($legacyUsers as $legacyUser) {
                    // Skip the email/agent code you've already used for the superadmin
                    if ($legacyUser->email_address === 'admin@nicare.com') {
                        continue;
                    }

                    // Build a staff profile for this legacy user
                    // Use the legacy first_name/surname/other_name if available, otherwise parse fullname
                    $firstName = $legacyUser->first_name ?: strtok($legacyUser->fullname, ' ');
                    $lastName  = $legacyUser->surname   ?: trim(str_replace($firstName, '', $legacyUser->fullname));
                    $middle    = $legacyUser->other_name ?: null;
                    $email     = $legacyUser->email_address ?: null;
                    $phone     = $legacyUser->phone_number ?: null;

                    $staff = Staff::updateOrCreate(
                        ['email' => $email], // fall back to phone or another unique key if email is null
                        [
                            'first_name'    => $firstName,
                            'last_name'     => $lastName,
                            'middle_name'   => $middle,
                            'email'         => $email,
                            'phone'         => $phone,
                            'designation_id'=> null,
                            'department_id' => null,
                            'address'       => null,
                        ]
                    );

                    // Create or update the user and associate it with the staff profile
                    $user = User::updateOrCreate(
                        ['username' => $legacyUser->nicare_code], // unique identifier
                        [
                            'name'          => trim($legacyUser->fullname),
                            'email'         => $email,
                            'username'      => $legacyUser->nicare_code,
                            'phone'         => $phone,
                            'password'      => Hash::make('password'),
                            'status'        => 1,
                            'userable_id'   => $staff->id,
                            'userable_type' => Staff::class,
                        ]
                    );

                    // Assign the System Admin role
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }

                $this->info('System Admin users migrated, staff profiles created, and role assigned.');


            // 7. Migrate pin inventory into premiums
            // first create payment invoices

              $premium_types = PremiumType::pluck('id', 'code')
                    ->toArray();

                foreach ($premium_types as $key => $value) {
                   $this->premium_type_array[strtolower($key)] = $value;
                }

              

            $this->createPaymentInvoices();
            // $this->updatePremiumUsedby();
            $legacyPins = DB::connection('legacy')->table('tbl_pin_inven')->get();
            foreach ($legacyPins as $pin) {

                $request = DB::connection('legacy')
                    ->table('tbl_request')
                    ->where('payment_id', $pin->payment_id)
                    ->first();

                $perPinAmount = 0;
                if ($request && $request->quantity && $request->quantity > 0) {
                    $perPinAmount = (float) $request->amount / (int) $request->quantity;
                }

              


                  $sector_id = $this->enrollee_type_array[strtolower($pin->category)];
            
               $newPremium = Premium::updateOrCreate(
                    ['pin' => $pin->pin_raw, 'serial_no' => $pin->serial_no],
                    [
                        'pin'          => $pin->pin_raw,
                        'premium_type_id'     => $this->premium_type_array[strtolower($pin->pin_type)] ?? null,
                        'sector_id' => $sector_id,
                        'benefit_package_id' => $this->standardPackage->id,
                        'amount'       => $perPinAmount,
                        'date_used'     => $pin->date_used,
                        'date_expired'  => $pin->date_expired,
                        'userable_type' => Enrollee::class,
                        'userable_id' => 0,
                        'status'        => strtolower($pin->status) === 'used' ? Status::USED()->value : Status::NOTUSED()->value,
                        'lga_id'        => $this->lga_array[$pin->lga],
                        'ward_id'       => $this->ward_array[$pin->ward],
                        'reference'    => $pin->payment_id,
                        'invoice_id'    => $this->paymentInvoice_array[$pin->request_id] ?? null,
                        'metadata'      => [
                            'legacy_id' => $pin->id,
                            'legacy_payment_id' => $pin->payment_id,
                            'user' => $pin->agent_reg_number,
                        ],
                    ]
                );

                $this->pin_array[$pin->id] = $newPremium->id;
            }
            $this->info('Premiums migrated.');


          



            // 8. Migrate enrollees
            $this->createVulnerableGroups();
            $this->createEnrollmentPhases();
            $this->migrateEnrollee('informal');
            // $this->createMdas();
            // $this->migrateEnrollee('formal');


            // end transaction
        });   

        $this->info('Legacy data migration completed successfully.');
    }



    protected function createEnrolleeTypes(){
        $sectors = Sector::all();
        
        foreach ($sectors as $sector) {
            $this->enrollee_type_array[strtolower($sector->name)] = $sector->id;
        }
    }


    protected function migrateEnrollee($sector = 'informal'){ 

        $table = $sector == 'informal' ? 'tbl_enrolee' : 'tbl_enrolee_formal';
          // Create Enrollee Types
            DB::connection('legacy')
            ->table($table)
            ->orderBy('id')
            ->chunkById(100, function ($legacyEnrollees) use ($sector) {
                foreach ($legacyEnrollees as $enrollee) {

                       

                $premium_id = null;
                if($enrollee->pin){
                    $premium_id = $this->pin_array[$enrollee->pin] ?? null;
                }
               
                // lookup enrollee type by name
                $sector_id = $this->enrollee_type_array[strtolower($sector)];
                if(!$sector_id){
                    $this->warn("Sector {$sector} not found");
                   continue;
                }

                 if(Enrollee::where('legacy_id', $enrollee->id)
                    ->where('sector_id', $sector_id)
                    ->exists()){
                    $this->info("Enrollee {$enrollee->enrolment_number} already exists");
                    continue;
                }

                $this->info("creating {$enrollee->enrolment_number} enrollee");

                $benefactor_id = $this->getBenefactorID($enrollee);
               
                $funding = $enrollee->funding ? $this->funding_array[$enrollee->funding] : $this->funding_array['premium'];

                $gender = match ($enrollee->sex) {
                    'Male', 'M' => 1,
                    'Female', 'F' => 2,
                    default => 1,
                };

                $marital_status = match ($enrollee->marital_status) {
                    'Single' => 1,
                    'Married' => 2,
                    'Divorced' => 3,
                    'Widowed' => 4,
                    default => 1,
                };

                $relationship_to_principal = match($enrollee->enrolee_category){
                    'Principal' => RelationshipToPrincipal::PRINCIPAL,
                    'Spouse' => RelationshipToPrincipal::SPOUSE,
                    'Child' => RelationshipToPrincipal::CHILD,
                    'Other' => RelationshipToPrincipal::OTHER,
                };

                $enrollee->status = $enrollee->enrolment_approval_status != 1 ? 100 : $enrollee->status;

                $status = match($enrollee->status){
                    1, '1' => Status::ACTIVE()->value,
                    2, '2' => Status::EXPIRED()->value,
                    0, '0' => Status::DELETED()->value,
                    default => Status::PENDING()->value,
                };

                $vulnerable_group_id = $this->vulnerable_groups_arr[strtolower($enrollee->vulnerability_status)] ?? $this->vulnerable_groups_arr['none'] ?? null;

                $enrollment_phase_id = $this->enrollment_phase_array[$enrollee->tracking][$benefactor_id] ?? null;
                $enrollee->mda_id = $this->mda_array[$enrollee->ministry] ?? null;

                $image_url = null;//$this->getImage($enrollee->id, $sector);
              
                // create enrollee
                $newEnrollee = Enrollee::updateOrCreate(
                    ['legacy_id' => $enrollee->id, 'sector_id' => $sector_id],
                    [   
                        
                        'enrollee_id' => $enrollee->enrolment_number,
                        'legacy_enrollee_id' => $enrollee->enrolment_number,
                        'benefit_package_id' => $enrollee->mode_of_enrolment == 'huwe' ? $this->BMPHSPackage->id : $this->standardPackage->id,
                        'premium_id' => $premium_id,
                        'nin'             => $enrollee->nin,
                        'benefactor_id'   => $benefactor_id,
                        'funding_type_id' => $funding,
                        'first_name'      => $enrollee->first_name,
                        'last_name'       => $enrollee->surname,
                        'middle_name'     => $enrollee->other_name,
                        'email'           => $enrollee->email_address,
                        'phone'           => $enrollee->phone_number,
                        'date_of_birth'   => $enrollee->date_of_birth,
                        'sex'          => $gender,
                        'marital_status'  => $marital_status,
                        'address'         => $enrollee->address ?? $enrollee->community,
                        'sector_id'=> $sector_id,
                        'relationship_to_principal'=> $relationship_to_principal,
                        'facility_id'     => $this->facility_array[$enrollee->provider_id],
                        'lga_id'          => $this->lga_array[$enrollee->lga],
                        'ward_id'         => $this->ward_array[$enrollee->ward],
                        'village'         => $enrollee->community,
                        'disability'      => $enrollee->disability,
                        'pregnant'        => $enrollee->pregnant,
                        'national_number'    => $enrollee->BHCPF_number ?? $enrollee->nin ?? null,
                        'vulnerable_group_id' => $vulnerable_group_id,
                        'status'          => $status,
                        'created_by'      => $this->superadmin->id,
                        'approved_by'     => $this->user_array[$enrollee->approved_by] ?? $this->superadmin->id,
                        'enrollment_phase_id' => $enrollment_phase_id,
                        'approval_date' => $enrollee->approved_date, 
                        'cno' => $enrollee->cno,
                        'occupation' => $enrollee->occupation,
                        'dfa' => $enrollee->date_of_first_appointment,
                        'basic_salary' => $enrollee->basic_salary,
                        'station' => $enrollee->station,
                        'salary_scheme' => $enrollee->salary_scheme,
                        'mda_id' => $enrollee->mda_id,
                        'enrollment_date' => $enrollee->enrol_date,
                        'capitation_start_date' => $enrollee->cap_date_month,
                        'created_at' => $enrollee->synced_datetime,
                        'updated_at' => now(),
                        'nok_name' => $enrollee->nok_name,
                        'nok_phone_number' => $enrollee->nok_phone_number,
                        'nok_address' => $enrollee->nok_address,
                        'nok_relationship' => $enrollee->nok_relationship,
                        'image_url' => $image_url
                      ]
                    );

                    if($enrollee->pin){
                        Premium::whereKey($premium_id)->update([
                            'userable_type' => Enrollee::class, 
                            'userable_id'   => $newEnrollee->id,
                        ]);
                    }
                }
            });
            $this->info('Enrollees migrated.');
    }

    

    protected function createPaymentInvoices(){
        $subscriptionType = PaymentCategory::where('code', 'subscription')->first();
        $reSubscriptionType = PaymentCategory::where('code', 're-subscription')->first();
        $paymentInvoices = DB::connection('legacy')->table('tbl_request')
            ->orderBy('sn')
            ->get();

       
            foreach ($paymentInvoices as $paymentInvoice) {

                $newPaymentInvoice = Invoice::updateOrCreate(
                    ['reference' => $paymentInvoice->payment_id],
                    [
                        'invoice_number' => uniqid(),
                        'description' => ucfirst($paymentInvoice->benefit_type)." ".ucfirst($paymentInvoice->pin_type)." PIN ",
                        'amount' => $paymentInvoice->amount,
                        'invoice_type' => 'createEnrolleeTypes',
                        'payment_date' => $paymentInvoice->payment_date,
                        'merchant_id' => 1,
                        'merchant_service_type_id' => 1,
                        'userable_type' => User::class,
                        'userable_id' => User::first()->id,
                        'payable_type' => PremiumType::class,
                        'payable_id' => $this->premium_type_array[strtolower($paymentInvoice->pin_type)],
                        'payment_catgory_id' => $paymentInvoice->request_type == 'new' ? $subscriptionType->id : $reSubscriptionType->id,
                        'metadata' => [
                            'legacy_id' => $paymentInvoice->sn,
                            'legacy_payment_id' => $paymentInvoice->payment_id,
                            'user' => $paymentInvoice->agent_reg_number,
                            'ward' => $paymentInvoice->ward
                        ],
                    ]
                );

                $this->paymentInvoice_array[$paymentInvoice->sn] = $newPaymentInvoice->id;
            }
            $this->info('Payment Invoices migrated.');
    }

    protected function updatePremiumUsedby(){
        $enrollees = DB::connection('legacy')->table('tbl_enrolee')
            ->select('id', 'pin', 'enrolment_number')
            ->distinct()
            ->whereNotNull('pin')
            ->where('pin', '<>', 0)
            ->get();

        foreach ($enrollees as $enrollee) {
            $premium_id = $enrollee->pin;
            $enrollee_id = $enrollee->id;

            $this->info("Updating used by of {$premium_id} to {$enrollee_id}");

            if ($premium_id) {
               $affected = DB::connection('legacy')
                ->table('tbl_pin_inven')
                ->where('id', $premium_id)
                ->update(['enrollee_id' => $enrollee_id]);

            }
        }

        //    $enrollees = DB::connection('legacy')->table('tbl_enrolee_formal')
        //     ->select('id', 'pin', 'cno')
        //     ->distinct()
        //     ->whereNotNull('pin')
        //     ->where('pin', '<>', 0)
        //     ->get();

        // foreach ($enrollees as $enrollee) {
        //     $premium_id = $enrollee->pin;
        //     $enrollee_id = $enrollee->id;

        //     $this->info("Updating used by of {$premium_id} to {$enrollee_id}");

        //     if ($premium_id) {
        //       DB::connection('legacy')
        //         ->table('tbl_pin_inven_formal')
        //         ->where('id', $premium_id)
        //         ->update(['enrollee_id' => $enrollee_id]);

        //     }
        // }

        



    }

    protected function createEnrollmentPhases(){
         /// enrolment phases
        $enrollees_phases = DB::connection('legacy')->table('tbl_enrolee')
            ->select('tracking', 'benefactor', 'mode_of_enrolment')
            ->distinct()
            ->orderBy('tracking')
            ->get();


            foreach ($enrollees_phases as $enrollee_phase) {

                $enrollee_phase->counterpart = null;
                if(!$enrollee_phase->benefactor){
                    continue;
                }

                if($enrollee_phase->tracking == '0' || $enrollee_phase->tracking == null){
                    continue;
                }
                
                $benefactor_id = $this->getBenefactorID($enrollee_phase);
                $this->info("creating {$enrollee_phase->tracking} of {$benefactor_id}");
                $newEnrolleePhase = EnrollmentPhase::updateOrCreate(
                    ['name' => "Phase $enrollee_phase->tracking", 'benefactor_id' => $benefactor_id],
                    [
                        'status'        => 1,
                    ]
                );
                $this->enrollment_phase_array[$enrollee_phase->tracking][$benefactor_id] = $newEnrolleePhase->id;
            }
    }

    protected function createMdas(){
       /// enrolment phases
        $ministries =  DB::connection('legacy')->table('tbl_enrolee_formal')
        ->distinct()
        ->pluck('ministry')
        ->toArray();
        foreach ($ministries as $ministry) {
            $this->info("creating {$ministry} mda");
            $newMda = Mda::updateOrCreate(
                ['name' => $ministry],
                [
                    'status'        => 1,
                ]
            );
            $this->mda_array[$ministry] = $newMda->id;
        }

    }


    protected function createVulnerableGroups(){
         $vulnerable_groups = [
            [ 'name' => '', 'code' => 'none'],
            [ 'name' => 'Children under 5yrs', 'code' =>  'cu5'],
            [ 'name' => 'Female Reproductive (15-45 years)', 'code' =>  'fra'],
            [ 'name' => 'Elderly (85 and above)', 'code' =>  'elder'],
            [ 'name' => 'Othes', 'code' => 'others'],
        ];

        foreach ($vulnerable_groups as $vulnerable_group) {

            $this->info("creating {$vulnerable_group['name']} vulnerable");
           $vulnerable = VulnerableGroup::updateOrCreate(
                ['name' => $vulnerable_group['name']],
                [
                    'code' => $vulnerable_group['code'],
                ]);

                $this->vulnerable_groups_arr[strtolower($vulnerable_group['name'])] = $vulnerable->id;
        }
    }

    protected function getBenefactorID($enrollee){

        //UPDATE `tbl_enrolee` SET benefactor = 'NGSCHA' WHERE mode_of_enrolment = 'huwe' AND benefactor IS NULL;
        // UPDATE `tbl_enrolee` SET benefactor = 'Self' WHERE mode_of_enrolment = 'premium' AND benefactor IS NULL;
        

         if(strlen($enrollee->benefactor) > 3){
                $enrollee->benefactor = match($enrollee->benefactor){
                    'Peculiar Cooperative' => '1',
                    'NGSCHA' => '3',
                    'Self' => '7',
                    'Nigeria For Women' => '6',
                    'Dr. MM Makusidi' => '5',
                    default => $enrollee->benefactor,
                };
            }

            $benefactor_id =  $this->benefactor_array[$enrollee->benefactor] ?? '7';

             if ($enrollee->counterpart == 'gac') {
                    if($this->gacCounterpart) {
                         $benefactor_id = $this->gacCounterpart->id;
                    }
                   
                }
            return $benefactor_id;
    }

   protected function getImage($id, $sector)
    {
        // Build the remote URL
        $remoteUrl = 'https://eims.ngscha.com/pictures/' . rawurlencode($id) . '/' . rawurlencode($sector);

        // Fetch the image (with retries + timeout)
        $response = Http::timeout(15)->retry(3, 500)->get($remoteUrl);

        if ($response->failed() || $response->body() === '' ) {
            throw new \RuntimeException("Failed to fetch image from {$remoteUrl}");
        }

        // Detect MIME → choose file extension
        $mime = strtolower(strtok((string) $response->header('Content-Type', 'image/jpeg'), ';'));
        $ext = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png'                  => 'png',
            'image/gif'                  => 'gif',
            'image/webp'                 => 'webp',
            default                      => 'jpg',
        };

        // Make a stable but unique-ish filename (prevents collisions)
        $hash = substr(sha1($response->body()), 0, 12);
        $path = "pictures/{$sector}/{$id}-{$hash}." . $ext;

        // Save to S3 (public bucket). If your bucket is private, set visibility to 'private'
        Storage::disk('s3')->put($path, $response->body(), [
            'visibility'  => 'public',     // change to 'private' if needed
            'ContentType' => $mime,
            'CacheControl'=> 'public, max-age=31536000, immutable',
        ]);

        // Return URL (for private buckets, use temporaryUrl instead)
        return Storage::disk('s3')->url($path);
        // For private buckets:
        // return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10));
    }

    
}
