<?php

// Script to add nicare_code to all export templates

$files = [
    'app/Exports/LaboratoryCasesExport.php' => [
        ['case_name' => 'Full Blood Count (FBC)', 'code' => 'NGSCHA/LAB/P/0001'],
        ['case_name' => 'Fasting Blood Sugar', 'code' => 'NGSCHA/LAB/P/0002'],
    ],
    'app/Exports/RadiologyCasesExport.php' => [
        ['case_name' => 'Chest X-Ray', 'code' => 'NGSCHA/RAD/P/0001'],
        ['case_name' => 'Abdominal Ultrasound', 'code' => 'NGSCHA/RAD/S/0001'],
    ],
    'app/Exports/ProfessionalServiceCasesExport.php' => [
        ['case_name' => 'Minor Surgery - Wound Suturing', 'code' => 'NGSCHA/PROF/S/0001'],
        ['case_name' => 'Incision and Drainage', 'code' => 'NGSCHA/PROF/S/0002'],
    ],
    'app/Exports/ConsultationCasesExport.php' => [
        ['case_name' => 'Specialist Consultation - Cardiology', 'code' => 'NGSCHA/CONS/S/0001'],
        ['case_name' => 'General Consultation', 'code' => 'NGSCHA/CONS/P/0001'],
    ],
    'app/Exports/ConsumableCasesExport.php' => [
        ['case_name' => 'Surgical Gloves - Sterile', 'code' => 'NGSCHA/CONSUM/P/0001'],
        ['case_name' => 'Gauze Swabs', 'code' => 'NGSCHA/CONSUM/P/0002'],
    ],
    'app/Exports/BundleCasesExport.php' => [
        ['case_name' => 'Normal Delivery Bundle', 'code' => 'NGSCHA/BUNDLE/S/0001'],
        ['case_name' => 'Caesarean Section Bundle', 'code' => 'NGSCHA/BUNDLE/S/0002'],
    ],
];

foreach ($files as $file => $cases) {
    echo "Processing: $file\n";
    
    $content = file_get_contents($file);
    
    foreach ($cases as $case) {
        // Add nicare_code after case_name
        $pattern = "/'case_name' => '{$case['case_name']}',/";
        $replacement = "'case_name' => '{$case['case_name']}',\n                    'nicare_code' => '{$case['code']}',";
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    // Update headings - add NiCare Code after Case Name
    $content = preg_replace(
        "/'Case Name \*',\n            'Service Description/",
        "'Case Name *',\n            'NiCare Code *',\n            'Service Description",
        $content
    );
    
    // Update map method - add nicare_code after case_name
    $content = preg_replace(
        "/\\\$case->case_name \?\? '',\n            \\\$case->service_description/",
        "\$case->case_name ?? '',\n            \$case->nicare_code ?? '',\n            \$case->service_description",
        $content
    );
    
    file_put_contents($file, $content);
    echo "✅ Updated: $file\n\n";
}

echo "All templates updated!\n";

