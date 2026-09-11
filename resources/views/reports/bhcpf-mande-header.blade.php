<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="UTF-8">
    @verbatim
    <!--[if gte mso 9]>
    <xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
        <x:Name>BHCPF Enrollees</x:Name>
        <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
    </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml>
    <![endif]-->
    @endverbatim
    <style>
        table { border-collapse: collapse; border: 2px solid #000000; }
        th { background-color: #CCCCCC; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px; }
        td { border: 1px solid #000000; padding: 3px; }
        .text { mso-number-format:"\@"; }
    </style>
</head>
<body>
<table border="1" cellspacing="0" cellpadding="3">
    <tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
