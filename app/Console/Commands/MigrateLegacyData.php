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
use App\Models\EnrolleeType;
use App\Models\EnrollmentPhase;
use App\Models\PremiumType;
use App\Models\Sector;
use App\Models\Staff;
use App\Models\VulnerableGroup;
use Illuminate\Database\Eloquent\Relations\Relation;

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
                        'baseline'       => $lgaRow->base_line,
                        'total_enrolled' => $lgaRow->total_enrolled
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
                        'enrollment_cap'  => $wardRow->enrolmentCap ?? 0,
                        'total_enrolled'  => $wardRow->total_enrolled,
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

            // 6. Migrate pin inventory into premiums
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

              

                $pin_category = match ($pin->category) {
                    'formal' => 1,
                    'informal' => 2,
                    'vulnerable' => 3,
                    'retiree' => 4,
                    default => 1,
                };

                $benefit_type = match ($pin->benefit_type) {
                    'basic' => 1,
                    'standard' => 2,
                    'premium' => 3,
                    default => 1,
                };

                // create premium types

                $premium_types = [
                    'individual' => ['premium_amount' => 16100],
                    'household' => ['premium_amount' => 43200],
                    'group' => ['premium_amount' => 43200],
                ];

                foreach ($premium_types as $type => $premium) {
                    $premium_type = PremiumType::updateOrCreate(
                        ['name' => $type],
                        [
                            'description' => null,
                            'premium_amount' => $premium['premium_amount'],
                        ]
                    );

                    $this->premium_type_array[strtolower($type)] = $premium_type->id;
                }
                
                
                
               $newPremium = Premium::updateOrCreate(
                    ['pin' => $pin->pin_raw, 'serial_no' => $pin->serial_no],
                    [
                        'pin'          => $pin->pin_raw,
                        'pin_type'     => $this->premium_type_array[strtolower($pin->pin_type)],
                        'pin_category' => $pin_category,
                        'benefit_type' => $benefit_type,
                        'amount'       => $perPinAmount,
                        'date_generated'=> $pin->date_generate,
                        'date_used'     => $pin->date_used,
                        'date_expired'  => $pin->date_expired,
                        'status'        => strtolower($pin->status) === 'used' ? 2 : 1,
                        'lga_id'        => $this->lga_array[$pin->lga],
                        'ward_id'       => $this->ward_array[$pin->ward],
                        'payment_id'    => $pin->payment_id,
                        'request_id'    => $pin->request_id,
                    ]
                );

                $this->pin_array[$pin->id] = $newPremium->id;
            }
            $this->info('Premiums migrated.');


            // 7. Create System Admin role and migrate users with user_role_id=1
            $role = Role::firstOrCreate([
                'name' => 'System Admin',
            ], [
                'description' => 'System Administrator',
                'status'      => 'active',
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
                    'address'         => null,
                    'status'          => 'active',
                ]
            );

            
            // User Profile
            // Create or update the super admin user and associate it with the staff profile
                $superadmin = User::updateOrCreate(
                    ['email' => 'admin@nicare.com'], // use email as unique key
                    [
                        'name'          => 'System Admin',
                        'phone'         => '08130051228',
                        'password'      => Hash::make('password'), // change to a secure value in production
                        'status'        => 'active',
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
                            'status'        => 'active',
                        ]
                    );

                    // Create or update the user and associate it with the staff profile
                    $user = User::updateOrCreate(
                        ['username' => $legacyUser->nicare_code], // unique identifier
                        [
                            'name'          => trim($legacyUser->fullname),
                            'email'         => $email,
                            'phone'         => $phone,
                            'password'      => Hash::make('password'),
                            'status'        => 'active',
                            'userable_id'   => $staff->id,
                            'userable_type' => Staff::class,
                        ]
                    );

                    // Assign the System Admin role
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }

                $this->info('System Admin users migrated, staff profiles created, and role assigned.');




            // 8. Migrate enrollees
            $this->createEnrolleeTypes();
            $this->migrateEnrollee('Informal');


            
        });

        $this->info('Legacy data migration completed successfully.');
    }



    protected function createEnrolleeTypes(){
        $sectors = Sector::all();
        
        foreach ($sectors as $sector) {
            $this->enrollee_type_array[$sector->name] = $sector->id;
        }
    }


    protected function migrateEnrollee($sector = 'informal'){ 
          // Create Enrollee Types
          

          $vulnerable_groups = [
            [ 'name' => '', 'code', 'none'],
            [ 'name' => 'Children under 5yrs', 'code', 'cu5'],
            [ 'name' => 'Female Reproductive (15-45 years)', 'code', 'fra'],
            [ 'name' => 'Elderly (85 and above)', 'code', 'elder'],
            [ 'name' => 'Othes', 'code', 'others'],
        ];

        foreach ($vulnerable_groups as $vulnerable_group) {
           $vulnerable = VulnerableGroup::updateOrCreate(
                ['name' => $vulnerable_group['name']],
                [
                    'code' => $vulnerable_group['code'],
                ]);

                $this->vulnerable_groups_arr[strtolower($vulnerable_group['name'])] = $vulnerable->id;
        }

            /// enrolment phases
            $enrollees_phases = DB::connection('legacy')->table('tbl_enrolee')
                ->select('tracking', 'benefactor')
                ->distinct()
                ->orderBy('tracking')
                ->get();

                foreach ($enrollees_phases as $enrollee_phase) {

                    $enrollee_phase->counterpart = null;

                    $benefactor_id = $this->getBenefactorID($enrollee_phase);
                    $newEnrolleePhase = EnrollmentPhase::updateOrCreate(
                        ['name' => $enrollee_phase->tracking],
                        [
                            'benefactor_id' => $benefactor_id,
                            'status'        => 1,
                        ]
                    );
                    $this->enrollment_phase_array[$enrollee_phase->tracking][$benefactor_id] = $newEnrolleePhase->id;
                }


          
            DB::connection('legacy')
            ->table('tbl_enrolee')
            ->orderBy('id')
            ->chunkById(100, function ($legacyEnrollees) use ($sector) {
                foreach ($legacyEnrollees as $enrollee) {
                // lookup enrollee type by name
                $sector_id = $this->enrollee_type_array[strtolower($sector)];

                $benefactor_id = $this->getBenefactorID($enrollee);
               
                $funding = $enrollee->funding ? $this->funding_array[$enrollee->funding] : null;

               

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

                $relationship_to_principal = RelationshipToPrincipal::from($enrollee->enrolee_category)->value;

                $enrollee->status = $enrollee->enrolment_approval_status != 1 ? 100 : $enrollee->status;

                $status = match($enrollee->status){
                    1, '1' => Status::ACTIVE,
                    2, '2' => Status::EXPIRED,
                    0, '0' => Status::DELETED,
                    default => Status::PENDING
                };

                $vulnerable_group_id = $this->vulnerable_groups_arr[$enrollee->vulnerability_status] ?? null;


              
                // create enrollee
                $newEnrollee = Enrollee::updateOrCreate(
                    ['legacy_id' => $enrollee->id],
                    [   
                        'enrollee_id' => $enrollee->enrolment_number,
                        'legacy_enrollee_id' => $enrollee->enrolment_number,
                        'nin'             => $enrollee->nin,
                        'benefactor_id'   => $benefactor_id,
                        'funding_type_id' => $funding,
                        'first_name'      => $enrollee->first_name,
                        'last_name'       => $enrollee->surname,
                        'middle_name'     => $enrollee->other_name,
                        'email'           => $enrollee->email_address,
                        'phone'           => $enrollee->phone_number,
                        'date_of_birth'   => $enrollee->date_of_birth,
                        'gender'          => $gender,
                        'marital_status'  => $marital_status,
                        'address'         => $enrollee->address ?? $enrollee->community,
                        'sector_id'=> $sector_id,
                        'relationship_to_principal'=> $relationship_to_principal,
                        'facility_id'     => $this->facility_array[$enrollee->provider_id],
                        'lga_id'          => $this->lga_array[$enrollee->lga],
                        'ward_id'         => $this->ward_array[$enrollee->ward],
                        'village'         => $enrollee->village,
                        'national_number'    => $enrollee->BHCPF_number,
                        'vulnerable_group_id' => $vulnerable_group_id,
                        'status'          => $status,
                        'created_by'      => $this->superadmin->id,
                        'approved_by'     => $this->user_array[$enrollee->approved_by] ?? $this->superadmin->id,
                      ]
                    );
                }
            });
            $this->info('Enrollees migrated.');
    }

    protected function getBenefactorID($enrollee){
         if(strlen($enrollee->benefactor) > 2){
                $enrollee->benefactor = match($enrollee->benefactor){
                    'Peculiar Cooperative' => '1',
                    'NGSCHA' => '3',
                    'Nigeria For Women' => '6',
                    'Dr. MM Makusidi' => '5',
                    default => $enrollee->benefactor,
                };
            }

            $benefactor_id = $enrollee->benefactor ? $this->benefactor_array[$enrollee->benefactor] : null;

             if ($enrollee->counterpart == 'gac') {
                    if($this->gacCounterpart) {
                         $benefactor_id = $this->gacCounterpart->id;
                    }
                   
                }
            return $benefactor_id;
    }
}
