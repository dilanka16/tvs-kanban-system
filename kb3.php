<?php
// --- Set UTF-8 Header ---
header("Content-Type: text/html; charset=UTF-8");

// SQL Server setup
$serverName = "";
$connectionOptions = [
    "Database" => "",
    "Uid" => "",
    "PWD" => "",
    "CharacterSet" => "UTF-8"
];

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// --- Time range: rolling 07:00 AM to next day 07:00 AM ---
date_default_timezone_set('Asia/Colombo');
$now = time();
$today7am = strtotime(date('Y-m-d 07:00:00'));

if ($now < $today7am) {
    $start = date('Y-m-d 07:00:00', strtotime('-1 day'));
    $end   = date('Y-m-d 07:00:00');
} else {
    $start = date('Y-m-d 07:00:00');
    $end   = date('Y-m-d 07:00:00', strtotime('+1 day'));
}

// --- Today's pre-7:00 AM time range ---
$todayStart = date('Y-m-d 00:00:00');
$todayPre7amEnd = date('Y-m-d 07:00:00');

// --- Tyre Size Normalization Function ---
function normalizeTyreSize($tyreSize) {
    $tyreSize = str_replace("�", "1/2", $tyreSize);
    $fractions = [
        "½" => "1/2",
        "¼" => "1/4",
        "¾" => "3/4",
        "⅛" => "1/8",
        "⅜" => "3/8",
        "⅝" => "5/8",
        "⅞" => "7/8"
    ];
    $tyreSize = strtr($tyreSize, $fractions);
    $tyreSize = preg_replace('/\s+/', ' ', trim($tyreSize));
    return $tyreSize;
}

// --- Curing Time Mapping with TyreType variations ---
function getCuringTime($tyreSize, $tyreType) {
    // Define TyreType categories
    $category1 = ['P72', 'P92', 'P9N', 'P7N', 'P93', 'P9N', 'RWSK1', 'SK24', 'SK1X', 'SKN1X'];
    $category2 = ['SK2', 'S42', 'M12', 'P12', 'SK2', 'SKN', 'RWS','P12X','P124'];
    $category3 = ['M2N', 'M4G', 'M4N'];
    $category4 = ['P5','D1','P6','P6N','P5N','D1N','P72','P92','P5G','P7N','P93','P9N','SK2','S42','M12','P12','SK2','M2N','M4G','M4N','SKN','RWS','RW6','MW4','RW2','SK1','M2G','RW6','P5G','RWSK1','E42X','P124','D1G','M2N24','P724','P7N24','RW2X','M4N2X','SKN1X','P12X','SK1X','SK24','M124','M12X','P724','R6'];

    // Base curing times for each tyre size
  $baseTimes = [
         // Tyre sizes with variations based on type
        "10.00-20 LUG" => ["4:45:00", "3:10:00", "3:40:00"],
        "12.00-20 LUG" => ["5:45:00", "3:30:00", "4:15:00"],
        "12.00-20SM" => ["6:05:00", "3:30:00", "4:15:00"],
        "140/55-9 LUG" => ["2:15:00", "1:10:00", "1:35:00"],
        "15X4½-8 LUG" => ["2:15:00", "1:10:00", "1:35:00"],
        "16X6-8 LUG" => ["2:20:00", "1:25:00", "1:40:00"],
        "180/60-10 LUG" => ["2:50:00", "1:45:00", "2:00:00"],
        "18X7-8 LUG" => ["2:50:00", "1:45:00", "2:00:00"],
        "250-15 LUG" => ["4:30:00", "2:35:00", "3:10:00"],
        "200/50-10 LUG" => ["3:00:00", "1:50:00", "2:10:00"],
        "21X8-9 LUG" => ["3:00:00", "1:50:00", "2:10:00"],
        "23X10-12 LUG" => ["4:30:00", "2:35:00", "3:10:00"],
        "23X12-12 LUG" => ["4:50:00", "3:00:00", "3:25:00"],
        "23X9-10 LUG" => ["3:30:00", "1:45:00", "2:30:00"],
        "27X10-12 LUG" => ["4:30:00", "2:35:00", "3:10:00"],
        "33X6-11 R4 AER RH EDC1378" => ["4:30:00", "2:35:00", "3:10:00"],
        "27X10-12NOAPT LUG" => ["4:30:00", "2:35:00", "3:10:00"],
        "27X10-12SM" => ["4:30:00", "2:35:00", "3:10:00"],
        "28X12½-15 LUG" => ["5:00:00", "4:00:00", "4:00:00"],
        "3.00-15 LUG" => ["4:45:00", "2:55:00", "3:20:00"],
        "3.00-15Y LUG" => ["4:45:00", "2:55:00", "3:20:00"],
        "30X10-16 R4 AER" => ["4:45:00", "2:55:00", "4:45:00"],
         "33X12-20 ND1 AER" => ["4:45:00", "2:55:00", "4:45:00"],
        "315/45-12 LUG" => ["4:50:00", "3:00:00", "3:25:00"],
        "355/65-15 LUG" => ["5:45:00", "4:00:00", "4:30:00"],
        "4.00-8 LUG" => ["2:00:00", "1:00:00", "1:30:00"],
        "4.00-8RIB" => ["1:45:00", "1:00:00", "1:30:00"],
        "400/60-15 LUG" => ["6:15:00", "4:00:00", "4:30:00"],
        "5.00-8 LUG" => ["2:00:00", "1:00:00", "1:30:00"],
        "5.00-8Y LUG" => ["2:00:00", "1:00:00", "1:30:00"],
        "6.00-9 LUG" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "6.00-9SM" => ["2:20:00", "1:25:00", "1:40:00"],
        "36X14-20 R4 AER RH" => ["5:20:00", "5:20:00", "5:20:00"],
        "6.00-9Y LUG" => ["2:20:00", "1:25:00", "1:40:00"],
        "6.50-10 LUG" => ["2:50:00", "1:45:00", "2:00:00"],
        "6.50-10Y LUG" => ["2:50:00", "1:45:00", "2:00:00"],
        "7.00-12 LUG" => ["3:00:00", "1:50:00", "2:10:00"],
        "7.00-12Y LUG" => ["3:00:00", "1:50:00", "2:10:00"],
        "7.00-15 LUG" => ["3:00:00", "1:50:00", "2:10:00"],
        "7.50-10 LUG" => ["3:30:00", "1:45:00", "2:30:00"],
        "7.50-15 LUG" => ["3:30:00", "1:45:00", "2:30:00"],
        "7.50-16 LUG" => ["3:00:00", "1:50:00", "2:10:00"],
        "8.15-15 LUG" => ["3:40:00", "2:15:00", "1:25:00"],
        "8.15-15Y LUG" => ["3:40:00", "2:15:00", "1:25:00"],
        "8.25-15 LUG" => ["3:40:00", "2:15:00", "1:25:00"],
        "9.00-20 LUG" => ["4:30:00", "3:10:00", "3:30:00"],
        "27X10-12WITHAPERTURELUG" => ["4:30:00", "2:35:00", "3:10:00"],
        "31X10X16R4 WITH APPERTURE" => ["4:45:00", "2:55:00", "2:55:00"],
        
        // Tyre sizes without variations (same for all types)
        "10½X5X6½ SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "10½X6X6½ SM" => ["2:10:00", "2:10:00", "2:10:00","2:10:00"],
        "10½X7X6½ SM" => ["2:30:00", "2:30:00", "2:30:00","2:30:00"],
        "10X3(2HOLE)RIB" => ["1:00:00", "1:00:00", "1:00:00","1:00:00"],
        "10X43/4X6½ SM" => ["1:50:00", "1:50:00", "1:50:00","1:50:00"],
        "10X4X6½ SAT" => ["1:30:00", "1:30:00", "1:30:00","1:30:00"],
        "10X4X6½ SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "10X4X6¼ SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "10X5X61/2 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "10X5X6½ ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "10X5X61/2 SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "10X5X6½ SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "10X5X6¼ ML" => ["1:15:00", "1:15:00", "1:15:00", "1:15:00"],
        "10X5X6¼ SM" => ["1:15:00", "1:15:00", "1:15:00"," 1:15:00"],
        "10X6X6¼ SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "125/50-75 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "12X4½(2HOLE)APWPRIME" => ["1:40:00", "1:40:00", "1:40:00", "1:40:00"],
        "12X41/2(2HOLE)S" => ["1:40:00", "1:40:00", "1:40:00", "1:40:00"],
        "12X4½(4HOLE)APWPRIME" => ["1:40:00", "1:40:00", "1:40:00", "1:40:00"],
        "12X4½(4HOLE)S" => ["1:40:00", "1:40:00", "1:40:00", "1:40:00"],
        "12X41/2X8 SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "12X4SJ-DISK" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "12X4SJ-WITHOUTDISKS" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "12X4X8_WITHOUT_BREAK" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "12X5½X8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "12X5X8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "12X6½X8 SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "13½X4½X8 ML" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "13½X4½X8 SAT" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "13½X4½X8 SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "13½X5½-8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "13½X5½X8 SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "131/2X51/2X8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "13½X5X8SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "13X3½X8ML" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "13X4½X8ML" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "13X4½X8SAT" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "13X4½X8SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "13X5½X8ML" => ["1:50:00", "1:50:00", "1:50:00","1:50:00"],
        "13X5½X8SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "13X5X8ML" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "13X5X8SAT" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "13X5X8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "14X4.5S" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "14X4½-8SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "14x4½x8 SAT" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "14X4½X8ML" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "14X4½X8SAT" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "14X41/2X8 SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "14X5X10ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "14X5X10SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "14X5X10 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        
        "150/50-100 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "150/75-100 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "15½X5X10 SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15½X5X10 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15½X6X10 ML" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "15½X6X10 SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "15X5(2HOLE)APWPRIME" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15X5(2HOLE)S" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15X5(4HOLE)APWPRIME" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15X5(4HOLE)S" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15x5x11¼ SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15X5X11¼ ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15X5X11¼ SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15X5X111/4 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "15X6X11¼ SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "15X6X11¼ SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "15X7X11¼ SM" => ["2:20:00", "2:20:00", "2:20:00",  "2:20:00"],
        "15X8X11¼ SM" => ["2:25:00", "2:25:00", "2:25:00", "2:25:00"],
        "16¼x5x11¼ SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16¼X5X11¼ ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16¼X5X11¼ SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16¼X5X11¼ SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16¼x6x11¼ SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16¼X6X11¼ ML" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16¼X6X11¼ SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16¼X6X11¼ SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16¼x7x11¼ SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16¼X7X11¼ ML" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16¼X7X11¼SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "161/4X7X111/4 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16¼X7X1114 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16X5JLGRIB" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16X5JLGS" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16X5RIB SJ WITHOUT DISK" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16X5SJ-DISKRIB" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16X5SKJ WITH DISK" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16X5SKJ WITHOUT DISK" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16x5x10½ SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "16X5X101/2 SM" => ["1:50:00", "1:50:00", "1:50:00","1:50:00"],
        "16X5X10½ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        
        "16X7X101/2 SM" => ["1:50:00", "1:50:00", "1:50:00","1:50:00"],
        "16X6X101/2  SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16x6x10½ SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "22X7 TR-S EDC667" => ["2:45:00", "2:45:00", "2:45:00", "2:45:00"],
        "16X6X10½ML" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16X6X101/2 SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16X6X101/2 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16X5X101/2 SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16X6X10½SM-BR" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16X6X11¼SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "16x7x10½ SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16x7x10½ SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16X7X10½ ML" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16X7X10½ SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "16X7X10½ SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "17X5X121/8 ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "17X5X121/8 SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "17X5X121/8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "17X6X121/8 SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "17X6X121/8 SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "180/75-120SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "18X5X121/8ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "18X5X121/8SAT" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "18X5X121/8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "18x6x121/8 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "18X6X121/8 ML" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "18X6X121/8 SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "18X6X121/8SAT-BR" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "18X6X121/8SM" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "18X7X121/8 SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "18X7X121/8ML" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "18X7X121/8SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "18X7X121/8 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "18x8x121/8 SAT" => ["2:25:00", "2:25:00", "2:25:00", "2:25:00"],
        "18X8X121/8 ML" => ["2:25:00", "2:25:00", "2:25:00", "2:25:00"],
        "18X8X121/8 SAT" => ["2:25:00", "2:25:00", "2:25:00", "2:25:00"],
        "18X8X121/8 SM" => ["2:25:00", "2:25:00", "2:25:00", "2:25:00"],
        "18X9X121/8 ML" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "18X9X121/8 SAT" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "18X9X121/8 SM" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "200/50-140 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "200/75-100 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
         "9X5X5 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "20X8X16 SAT" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "20X8X16 SM" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "20x9x16 SAT" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "20X9X16 SM" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "21X5X15 ML" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "21X5X15 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "21x6x15 SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "21X6X15SAT" => ["2:10:00", "2:10:00", "2:10:00", "2:10:00"],
        "21x7x15 SAT" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "21x7x15 SM" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "21X7X15 ML" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "28X16X22 SM" => ["5:00:00", "5:00:00", "5:00:00", "5:00:00"],
        "40X16X30 SM" => ["6:00:00", "6:00:00", "6:00:00", "6:00:00"],
       
        "21X7X15 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "21x8x15 SAT" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "21x8x15 SM" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "21X8X15ML" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "21X8X15 SAT" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "21X8X15SM" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "21x9x15 SAT" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "21X9X15 ML" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "21X9X15 SAT" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "21X9X15 SM" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "22x10x16(559x254-406)_SM_EDC1452" => ["3:30:00", "3:30:00", "3:30:00", "3:30:00"],
        "22X10X16 ML" => ["3:30:00", "3:30:00", "3:30:00", "3:30:00"],
        "22X10X16 SAT" => ["3:30:00", "3:30:00", "3:30:00", "3:30:00"],
        "22X10X16 SM" => ["3:30:00", "3:30:00", "3:30:00", "3:30:00"],
        "22x12x16 SM" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "22x12x16(559x305-406)_SM_EDC1446" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "22X12X16 ML" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "22X12X16 SAT" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "22X12X16SM" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "22X12X173/4 SM" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "22x14x16(559x356-406)_SM_EDC1335" => ["4:30:00", "4:30:00", "4:30:00", "4:30:00"],
        "22x14x16(559x356-406)_SM_EDC1447" => ["4:30:00", "4:30:00", "4:30:00", "4:30:00"],
        "22X14X16 ML" => ["4:30:00", "4:30:00", "4:30:00", "4:30:00"],
        "22X14X16 SM" => ["4:30:00", "4:30:00", "4:30:00", "4:30:00"],
        "22X16X16 SM" => ["5:00:00", "5:00:00", "5:00:00", "5:00:00"],
        "22X6X16 ML" => ["2:30:00", "2:30:00", "2:30:00", "2:30:00"],
        "22X6X16 SAT" => ["2:30:00", "2:30:00", "2:30:00", "2:30:00"],
        "22X7(8HOLE)S" => ["2:45:00", "2:45:00", "2:45:00", "2:45:00"],
        "22X7(9HOLE)S" => ["2:45:00", "2:45:00", "2:45:00", "2:45:00"],
        "22X7X16 ML" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "22X7X16 SAT" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "22X7X16 SM" => ["2:35:00", "2:35:00", "2:35:00", "2:35:00"],
        "22x8x16 SAT" => ["2:50:00", "2:50:00", "2:50:00", "2:50:00"],
        "22X8X16 ML" => ["2:50:00", "2:50:00", "2:50:00", "2:50:00"],
        "22X8X16SAT" => ["2:50:00", "2:50:00", "2:50:00", "2:50:00"],
        "22X8X16 SM" => ["2:50:00", "2:50:00", "2:50:00", "2:50:00"],
        "22x9x16 SAT" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "22X9X16 ML" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "22X9X16 SAT" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "22X9X16 SM" => ["3:00:00", "3:00:00", "3:00:00", "3:00:00"],
        "230/75-120 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "230/75-170 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "230/85-170 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "250/100-190 SM" => ["1:20:00", "1:20:00", "1:20:00", "1:20:00"],
        "250/105-170 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "250/130-140 SM" => ["1:40:00", "1:40:00", "1:40:00", "1:40:00"],
        "250/60-190 SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "250/80-170SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "250/85-190SM" => ["1:00:00", "1:00:00", "1:00:00", "1:00:00"],
        "250/90-150SM" => ["1:10:00", "1:10:00", "1:10:00", "1:10:00"],
        "254/90-145SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "255/120-169SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "285/75-220SM" => ["1:30:00", "1:30:00", "1:30:00", "1:30:00"],
        "28X10X22 SM" => ["3:30:00", "3:30:00", "3:30:00", "3:30:00"],
        "28X12X22 ML" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "28X12X22 SAT" => ["4:00:00", "4:00:00", "4:00:00", "4:00:00"],
        "28X14X22 SM" => ["4:30:00", "4:30:00", "4:30:00", "4:30:00"],
        "16X6-8_ND_WITH_APPERTURE" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "140/55-9 LUG" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "15X41/2-8 LUG" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "4.00-8 RIB" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "33X6-11 R4 AER LH EDC1378" => ["4:30:00", "4:30:00", "4:30:00", "4:30:00"],
        "161/4X7X111/4 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "161/4X6X111/4 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "550/200-410 SM" => ["2:20:00", "2:20:00", "2:20:00", "2:20:00"],
        "36X14-20 R4 AER LH" => ["3:45:00", "3:45:00", "3:45:00", "3:45:00"],
        "405/130-305 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
         "10X5X61/2 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "12X41/2X8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "14X41/2X8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],
        "12X51/2X8 SM" => ["1:50:00", "1:50:00", "1:50:00", "1:50:00"],

          ];
    

    
    // Check if we have this tyre size in our mapping
    if (isset($baseTimes[$tyreSize])) {
        $times = $baseTimes[$tyreSize];
        $elementCount = count($times);
        
        // Determine which category the tyre type belongs to
        if (in_array($tyreType, $category1)) {
            return $times[0]; // First element for category1
        } elseif (in_array($tyreType, $category2)) {
            return $times[1]; // Second element for category2
        } elseif (in_array($tyreType, $category3)) {
            return $times[2]; // Third element for category3
        } elseif (in_array($tyreType, $category4) && $elementCount === 4) {
            return $times[3]; // Fourth element for category4 (only if array has 4 elements)
        } else {
            // Default to first category if type not found or category4 but only 3 elements
            return $times[0];
        }
    }
    
    // Return default if not found
    return "N/A";
}

// Function to fetch and process data
function fetchAndProcessData($conn, $start, $end) {
    $sql = "
        SELECT 
           s3.Press, 
           s3.StencilNo,
           s3.TyreSize,
           s3.TyreType,
           s3.Process_Id,
           s3.Loaded_date,
           s3.Unloaded_Date
        FROM ProductionDetails_Stage3 s3
        LEFT JOIN ProductionDetails pd 
            ON s3.StencilNo = pd.StencilNo
        WHERE s3.Loaded_date >= ? AND s3.Loaded_date < ?
        ORDER BY s3.Press, s3.Loaded_date
    ";
    $params = [$start, $end];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // Group Data by Press + Merge Loaded times within 1 minute
    $grouped = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $press = $row['Press'] ?? '';
        $tyresize = normalizeTyreSize($row['TyreSize'] ?? '');
        $tyretype = $row['TyreType'] ?? '';
        $curingTime = getCuringTime($tyresize, $tyretype);

        $loadedTimeObj = ($row['Loaded_date'] instanceof DateTime) ? $row['Loaded_date'] : null;
        $loaded = $loadedTimeObj ? $loadedTimeObj->format('Y-m-d H:i:s') : '';

        $foundKey = null;
        foreach ($grouped as $key => $g) {
            if ($g['Press'] === $press) {
                $existingTime = strtotime($g['Loaded']);
                $currentTime = strtotime($loaded);
                if (abs($currentTime - $existingTime) <= 1200) {
                    $foundKey = $key;
                    if ($currentTime > $existingTime) {
                        $grouped[$key]['Loaded'] = $loaded;
                    }
                    break;
                }
            }
        }

        if ($foundKey === null) {
            $key = $press . '|' . $loaded;
            $grouped[$key] = [
                'Press' => $press,
                'Loaded' => $loaded,
                'Unloaded' => ($row['Unloaded_Date'] instanceof DateTime) ? $row['Unloaded_Date']->format('Y-m-d H:i:s') : '',
                'StencilNo' => [],
                'TyreSize' => [],
                'TyreType' => [],
                'Process_Id' => [],
                'CuringTime' => $curingTime
            ];
            $foundKey = $key;
        }

        $grouped[$foundKey]['StencilNo'][] = $row['StencilNo'];
        $grouped[$foundKey]['TyreSize'][] = $tyresize;
        $grouped[$foundKey]['TyreType'][] = $tyretype;
        $grouped[$foundKey]['Process_Id'][] = $row['Process_Id'];
    }
    
    return $grouped;
}

// Get data for both time ranges
$currentData = fetchAndProcessData($conn, $start, $end);
$pre7amData = fetchAndProcessData($conn, $todayStart, $todayPre7amEnd);

// Function to generate HTML table with press-wise display
function generatePressWiseTable($data, $title, $nextShiftFirstLoadedTimes = [], $isCurrentShift = false) {
    $now = new DateTime(); // current computer time
    $sevenAM = new DateTime('07:00');

    // Prepare all rows with calculated plan times
    $allRows = [];
    foreach ($data as $g) {
        if ($g['Loaded'] && $g['CuringTime'] && $g['CuringTime'] != "N/A") {
            $loadedTime = new DateTime($g['Loaded']);
            $parts = explode(':', $g['CuringTime']);
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];
            $seconds = (int)$parts[2];
            $loadedTime->add(new DateInterval("PT{$hours}H{$minutes}M{$seconds}S"));
            $nextTyre = $loadedTime->format('H:i');

            // PLAN TIME = NEXT TYRE + 45 minutes
            $planTime = clone $loadedTime;
            $planTime->add(new DateInterval("PT45M"));
            $planTimeStr = $planTime->format('H:i');
            
            // Convert plan time to DateTime for comparison
            $planTimeObj = DateTime::createFromFormat('H:i', $planTimeStr);
            
            // For early shift, skip entries where plan time is after 07:00
            if (!$isCurrentShift && $planTimeObj >= $sevenAM) {
                continue;
            }
            
            // For current shift, skip entries where plan time is before 07:00
            if ($isCurrentShift && $planTimeObj < $sevenAM) {
                continue;
            }
        } else {
            $nextTyre = '--:--';
            $planTimeStr = '--:--';
            $planTimeObj = null;
            $loadedTime = null;
        }

        $allRows[] = [
            'Press' => $g['Press'],
            'Loaded' => $g['Loaded'],
            'NextTyre' => $nextTyre,
            'PlanTime' => $planTimeStr,
            'PlanTimeObj' => $planTimeObj,
            'NextTyreObj' => $loadedTime,
            'OriginalData' => $g
        ];
    }

    // Sort all rows by PLAN TIME ascending
    usort($allRows, function($a, $b) {
        if ($a['PlanTimeObj'] === null && $b['PlanTimeObj'] === null) return 0;
        if ($a['PlanTimeObj'] === null) return 1;
        if ($b['PlanTimeObj'] === null) return -1;
        return $a['PlanTimeObj'] <=> $b['PlanTimeObj'];
    });

    // Group by press
    $pressData = [];
    foreach ($allRows as $row) {
        $press = $row['Press'];
        if (!isset($pressData[$press])) {
            $pressData[$press] = [];
        }
        $pressData[$press][] = $row;
    }

    $html = '<div class="table-container">';
    $html .= '<h3>' . htmlspecialchars($title) . '</h3>';
    
    // Create a grid of press boxes
    $html .= '<div class="press-grid">';
    
    foreach ($pressData as $press => $rows) {
        // Get the latest row for this press
        $latestRow = end($rows);
        
        // Calculate status
        $status = 'ON GOING';
        $statusClass = 'boarding';
        
        if ($latestRow['NextTyre'] !== '--:--' && $latestRow['NextTyreObj'] instanceof DateTime) {
            $nextTyreTimestamp = $latestRow['NextTyreObj']->getTimestamp();
            $nowTimestamp = $now->getTimestamp();

            // Check if Next Tyre Time is greater than current time (DELAYED)
            if ($nextTyreTimestamp < $nowTimestamp) {
                $status = 'DELAYED';
                $statusClass = 'delayed';
            } 
            // Check if plan time has passed (NOT START YET)
            else if ($latestRow['PlanTime'] !== '--:--') {
                $planTimestamp = strtotime(date('Y-m-d') . ' ' . $latestRow['PlanTime']);
                if ($nowTimestamp > $planTimestamp) {
                    $status = 'NOT START YET';
                    $statusClass = 'gate-closing';
                }
            }
        }

        $html .= '<div class="press-box">';
        $html .= '<div class="press-header">PRESS ' . htmlspecialchars($press) . '</div>';
        $html .= '<div class="next-tyre-time">' . $latestRow['NextTyre'] . '</div>';
        $html .= '<div class="press-status ' . $statusClass . '">' . $status . '</div>';
        $html .= '</div>'; // press-box
    }
    
    $html .= '</div>'; // press-grid
    $html .= '</div>'; // table-container
    
    return $html;
}

// Function to filter data for current shift (includes entries from early shift with PLAN TIME > 07:00)
function prepareCurrentShiftData($currentData, $pre7amData) {
    $sevenAM = new DateTime('07:00');
    $combinedData = [];
    
    // First add all current shift data
    foreach ($currentData as $item) {
        $combinedData[] = $item;
    }
    
    // Then add early shift entries with PLAN TIME > 07:00
    foreach ($pre7amData as $item) {
        if ($item['Loaded'] && $item['CuringTime'] && $item['CuringTime'] != "N/A") {
            $loadedTime = new DateTime($item['Loaded']);
            $parts = explode(':', $item['CuringTime']);
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];
            $seconds = (int)$parts[2];
            $loadedTime->add(new DateInterval("PT{$hours}H{$minutes}M{$seconds}S"));
            $planTime = clone $loadedTime;
            $planTime->add(new DateInterval("PT30M"));
            
            if ($planTime >= $sevenAM) {
                $combinedData[] = $item;
            }
        }
    }
    
    return $combinedData;
}

// Prepare the data for display
$currentShiftData = prepareCurrentShiftData($currentData, $pre7amData);

// Get first loaded times from current shift for each press
$firstLoadedTimesCurrentShift = [];
foreach ($currentData as $g) {
    $press = $g['Press'];
    if (!isset($firstLoadedTimesCurrentShift[$press])) {
        $firstLoadedTimesCurrentShift[$press] = date('H:i', strtotime($g['Loaded']));
    }
}

// --- HTML Output ---
echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>KAN BAN</title>
<style>
/* --- Press Display Style --- */
body {
    font-family: Arial, sans-serif;
    background-color: #f0f0f0;
    color: #333;
    margin: 0;
    padding: 20px;
}

/* Header */
h2 {
    text-align: center;
    color: #2c3e50;
    font-size: 2.5em;
    margin: 20px 0 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
}

h3 {
    text-align: center;
    color: #3498db;
    font-size: 1.8em;
    margin: 15px 0;
}

.date-range {
    text-align: center;
    font-size: 1.2em;
    color: #7f8c8d;
    margin-bottom: 20px;
}

/* Sticky header */
.header-section {
    position: sticky;
    top: 0;
    background-color: #f0f0f0;
    z-index: 100;
    padding: 10px 0;
    border-bottom: 2px solid #ddd;
}

.clock-container {
    text-align: center;
    padding: 10px 20px;
    font-size: 2em;
    color: #e74c3c;
    background-color: #fff;
    border-radius: 5px;
    margin: 10px auto;
    max-width: 200px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* Press Grid */
.press-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.press-box {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s ease;
    text-align: center;
}

.press-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.press-header {
    background-color: #3498db;
    color: white;
    padding: 15px;
    font-size: 1.4em;
    font-weight: bold;
}

.next-tyre-time {
    background-color: #2c3e50;
    color: white;
    padding: 30px 15px;
    font-size: 3em;
    font-weight: bold;
}

.press-status {
    padding: 20px 15px;
    font-size: 1.5em;
    font-weight: bold;
}

/* Status Colors */
.boarding {
    color: #f39c12;
    background-color: #fffbf0;
}

.gate-closing {
    color: #e74c3c;
    background-color: #fee;
    animation: blink 1s infinite;
}

.on-time {
    color: #27ae60;
    background-color: #f0fff0;
}

.delayed {
   .delayed {
    color: #e74c3c;
    background-color: #fee;
    animation: blink 1s infinite;
}
}

.cancelled {
    color: #95a5a6;
    background-color: #f8f8f8;
    text-decoration: line-through;
}

/* Animation */
@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .press-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
    
    h2 {
        font-size: 1.8em;
    }
    
    h3 {
        font-size: 1.4em;
    }
    
    .next-tyre-time {
        font-size: 2.5em;
        padding: 25px 15px;
    }
    
    .press-status {
        font-size: 1.3em;
        padding: 15px;
    }
}

/* Table container */
.table-container {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    margin: 20px auto;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>
</head>
<body>

<!-- Header Section at the top -->
<div class="header-section">
    <h2>KANBAN SYSTEM</h2>
    <div class="date-range">Current Shift: From ' . date('Y-m-d H:i', strtotime($start)) . ' to ' . date('Y-m-d H:i', strtotime($end)) . '</div>
    <div class="clock-container" id="liveClock">' . date('H:i') . '</div>
</div>';

// Display current shift data (includes entries from early shift with PLAN TIME > 07:00)
echo generatePressWiseTable($currentShiftData, 'CURRENT SHIFT: ' . date('H:i', strtotime($start)) . ' - ' . date('H:i', strtotime($end)), [], true);

echo '<script>
setTimeout(function() {
    window.location.reload();
}, 60000);

function updateClock() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, "0");
    const minutes = now.getMinutes().toString().padStart(2, "0");
    document.getElementById("liveClock").textContent = hours + ":" + minutes;
}
setInterval(updateClock, 1000);
</script>
</body>
</html>';