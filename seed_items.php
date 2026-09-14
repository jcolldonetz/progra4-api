<?php

/**
 * Seeder: inserta N items de informática en la tabla items de SQLite.
 *
 * Uso:  php seed_items.php [cantidad] [--reset]   (desde progra4-api/)
 *
 *   cantidad   cuántos items crear (por defecto 1000). Debe ser un entero
 *              positivo; no se crean nombres de más, así que si el catálogo
 *              no alcanza, se rellena con variaciones genéricas.
 *   --reset    borra los existentes antes de insertar.
 *
 * Ejemplos:
 *   php seed_items.php               -> 1000 items
 *   php seed_items.php 500           -> 500 items
 *   php seed_items.php 500 --reset   -> borra y crea 500 items
 *
 * El script también crea la tabla categorias con las 8 categorías del
 * catálogo y asocia cada item a la suya (relación 1:N hacia la columna
 * items.categoria_id, que se agrega con ALTER TABLE si la BD es vieja).
 * Los nombres son case-insensitive únicos (como exige ItemService).
 */

declare(strict_types=1);

$dbFile = __DIR__ . '/data/items.sqlite';

if (!is_file($dbFile)) {
    fwrite(STDERR, "No se encontró {$dbFile}\n");
    exit(1);
}

$reset = in_array('--reset', $argv, true);

// -- Cantidad objetivo: primer argumento que no sea --reset --
$cantidad = 1000;
foreach ($argv as $arg) {
    if ($arg === '--reset') {
        continue;
    }
    if (filter_var($arg, FILTER_VALIDATE_INT) !== false && (int) $arg > 0) {
        $cantidad = (int) $arg;
        break;
    }
}

$pdo = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// -- Esquema: tabla de categorias + columna categoria_id en items -----------
// (SQLite no admite "ADD COLUMN IF NOT EXISTS": se consulta PRAGMA).
$pdo->exec('CREATE TABLE IF NOT EXISTS categorias (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL)');

$cols = $pdo->query('PRAGMA table_info(items)')->fetchAll();
$hasCategoriaId = false;
foreach ($cols as $c) {
    if (($c['name'] ?? '') === 'categoria_id') {
        $hasCategoriaId = true;
        break;
    }
}
if (!$hasCategoriaId) {
    $pdo->exec('ALTER TABLE items ADD COLUMN categoria_id INTEGER REFERENCES categorias(id)');
}

// -- Limpiar solo si se pide --reset --
if ($reset) {
    $pdo->exec('DELETE FROM items');
    echo "Tabla items vaciada.\n";
}

// =========================================================================
// Catálogo de productos informáticos
// =========================================================================

/** @return array<array{0: string, 1: list<string>}> [marca, modelos] */
function catalogo(): array
{
    return [
        'Monitores' => [
            ['Samsung',  ['LS24A600 24" FHD', 'S27A600 27" QHD', 'Odyssey G5 27" 165Hz', 'Odyssey G7 32" 240Hz', 'ViewFinity S8 27" 4K', 'F27T70 27" FHD', 'C27J60 27" Curvo', 'CF39 24" Curvo', 'LU28R55 28" 4K', 'M5 24" Smart Monitor', 'M7 32" Smart Monitor', 'LS34A650 34" Ultrawide', 'Odyssey G9 49" Super Ultrawide']],
            ['LG',      ['27GN800-B 27" IPS', '27GL850-B 27" Nano IPS', '34GP83A-B 34" Ultrawide', '27UP600 27" 4K HDR', '32GN600 32" FHD', '22MK430 22" FHD', '27WL500 27" FHD', '29WN600 29" Ultrawide', '32UN880 32" 4K USB-C', 'UltraGear 27GP850 27" 165Hz', '43UN700 43" 4K', '24MP400 24" FHD', '27MP400 27" FHD']],
            ['Dell',   ['S2722QC 27" 4K', 'S2721DGF 27" 165Hz', 'S2421HN 24" Curvo', 'P2723QE 27" 4K USB-C', 'U2723QE 27" 4K IPS Black', 'G2722HT 27" 165Hz', 'SE2723 27" FHD', 'E2723HN 27" FHD', 'C2722DE 27" 4K USB-C Hub', 'U2722D 27" QHD', 'P2422H 24" FHD', 'S2721QS 27" 4K']],
            ['HP',     ['M24fw 24" FHD', 'M27fw 27" FHD', 'X27c 27" Curvo 165Hz', 'Omen 27 27" 165Hz', 'Omen 27i 27" 165Hz IPS', 'E243m 24" FHD USB-C', 'Z27 27" 4K', 'V24i 24" FHD', 'V27i 27" FHD', 'P244 24" FHD', 'P274 27" FHD', 'U28 28" 4K']],
            ['Acer',   ['KG241 24" FHD', 'Nitro VG240Y 24" 144Hz', 'Nitro XV272U 27" 144Hz', 'Predator X27 27" 4K 144Hz', 'EB321 32" FHD', 'CB272 27" FHD', 'R240HY 24" FHD', 'S271HL 27" FHD', 'C272 27" Curvo', 'KG271 27" FHD', 'XZ322Q 32" Curvo 165Hz', 'Nitro XV240Y 23.8" 165Hz']],
            ['BenQ',   ['EW2880U 28" 4K', 'EW3280U 32" 4K', 'PD2700U 27" 4K', 'MOBIUZ EX2710U 27" 4K 144Hz', 'EX2510S 25" 144Hz', 'EX2710Q 27" 165Hz', 'GW2480 24" FHD', 'GW2780 27" FHD', 'BL2711U 27" 4K', 'PD2705U 27" 4K USB-C', 'RD280U 28" 4K']],
            ['ASUS',   ['VG249Q 24" 144Hz', 'VG27AQ 27" 165Hz', 'VG27AQ1A 27" 170Hz', 'TUF VG259QM 25" 280Hz', 'ROG Swift PG279Q 27" 165Hz', 'ROG Swift PG32UQ 32" 4K 144Hz', 'ProArt PA278QV 27" QHD', 'VC239H 23" FHD', 'VA24EHE 24" FHD', 'VS228H 21.5" FHD', 'ROG Strix XG27AQM 27" 270Hz']],
            ['ViewSonic', ['VX2476 24" FHD', 'VX2776 27" QHD', 'VG2455 24" FHD USB-C', 'XG2405 24" 144Hz', 'XG2705 27" 144Hz', 'TD2455 24" Tactil', 'VP2776 27" QHD', 'VX3276 32" QHD', 'VX2718 27" Curvo 165Hz', 'XG2431 24" 240Hz']],
            ['Philips', ['242E1GAE 24" 144Hz', '275M1RZ 27" 170Hz', '241E1S 24" FHD', '271E1CA 27" Curvo', '325M1RZ 32" 170Hz', '242B9N 24" FHD', 'B-Line 241B 24" FHD', '272P 27" QHD USB-C', '346B1C 34" Ultrawide', 'E-Line 241E 24" FHD']],
            ['AOC',    ['24G2 24" 144Hz', '27G2 27" 144Hz', '24G2SP 24" 165Hz', '27G2SP 27" 165Hz', 'U28P2U 28" 4K', 'CQ27G2 27" Curvo 144Hz', 'C32G2 32" Curvo 165Hz', '22V2H 22" FHD', '24B2XH 24" FHD', 'Q27G2S 27" QHD 155Hz', 'CU27G3S 27" Curvo 240Hz', 'AGON AG275QXN 27" QHD 165Hz']],
            ['Huawei', ['MateView 28.2" 4K', 'MateView SE 23.8" FHD', 'MateView GT 34" Ultrawide', 'MateView GT 27" 165Hz', 'AD80HW 23.8" FHD', 'MateStation 23.8" FHD']],
            ['Xiaomi', ['Mi Monitor 27" 165Hz', 'Mi Ultrawide 34" 144Hz', 'Mi Curved Gaming 34" 144Hz', 'Redmi Monitor 24" FHD', 'Redmi Monitor 27" FHD', 'G27i 27" 165Hz']],
            ['Lenovo', ['ThinkVision T24i-10 24" FHD', 'ThinkVision T27p-30 27" 4K', 'Legion Y25-30 25" 240Hz', 'G27-20 27" 144Hz', 'C27-30 27" Curvo', 'L24i-30 24" FHD', 'ThinkVision P27u-20 27" 4K', 'M27q 27" QHD 170Hz', 'Q27q-20 27" QHD Curvo']],
            ['MSI',    ['Optix MAG274QRF-QD 27" QHD 165Hz', 'Optix MAG275R 27" 144Hz', 'Optix MAG27CQ 27" Curvo 144Hz', 'Optix MPG341QR 34" Ultrawide', 'PRO MP241 24" FHD', 'PRO MP275Q 27" QHD', 'Oculux NXG253R 24.5" 360Hz']],
            ['Gigabyte', ['M27Q 27" QHD 170Hz', 'M32Q 32" QHD 165Hz', 'M28U 28" 4K 144Hz', 'G27Q 27" QHD 144Hz', 'G32QC 32" Curvo 165Hz', 'AORUS FI32U 32" 4K 144Hz', 'AORUS CV27F 27" Curvo 165Hz', 'M34WQ 34" Ultrawide 144Hz']],
        ],

        'Auriculares' => [
            ['Sony',        ['WH-1000XM5', 'WH-1000XM4', 'WF-1000XM4', 'WF-1000XM5', 'WH-CH720N', 'WH-CH520', 'MDR-7506', 'INZONE H9', 'INZONE H7', 'WF-C500', 'WF-SP800N', 'WH-XB910N', 'WH-XB510AS', 'WF-L900']],
            ['JBL',         ['Tune 770NC', 'Tune 520BT', 'Tune 510BT', 'Live 660NC', 'Quantum 800', 'Quantum 350', 'Live Pro 2', 'Tune 130NC', 'Wave 100', 'Wave Beam', 'Clip 4', 'Go 3', 'Tune 230NC', 'Vibe 100']],
            ['Bose',        ['QuietComfort Ultra', 'QuietComfort 45', 'QuietComfort Earbuds II', 'SoundLink Max', 'SoundLink Flex', 'Sport Earbuds', 'SoundSport', 'SoundLink Around-Ear II', 'Noise Cancelling 700']],
            ['Samsung',     ['Galaxy Buds2 Pro', 'Galaxy Buds FE', 'Galaxy Buds2', 'Level U2', 'Galaxy Buds Pro', 'Galaxy Buds+', 'Level Active']],
            ['HyperX',      ['Cloud II', 'Cloud III', 'Cloud Alpha', 'Cloud Stinger', 'Cloud Stinger Core', 'Cloud Buds', 'Cloud Mix Buds', 'QuadCast', 'Pulsefire Haste', 'Cloud Orbit S']],
            ['Razer',       ['Barracuda X', 'BlackShark V2', 'Kraken V3', 'Kraken V3 HyperSense', 'Basilisk V3', 'Nari', 'Opus', 'Hammerhead Pro', 'Kraken BT', 'BlackWidow V3']],
            ['Corsair',     ['HS80 RGB', 'Virtuoso RGB', 'HS65 Surround', 'HS35', 'VOID RGB Elite', 'HS50', 'Dark Core RGB SE', 'MM700', 'Virtuoso RGB Wireless XT', 'HS80 MAX']],
            ['SteelSeries', ['Arctis Nova Pro', 'Arctis Nova 7', 'Arctis Nova 5', 'Arctis 7+', 'Arctis Prime', 'Arctis 1', 'Arctis 3', 'Arctis 5', 'Nova Pro Wireless', 'Arena 9']],
            ['Audio-Technica', ['ATH-M50xBT2', 'ATH-M50x', 'ATH-M40x', 'ATH-ANC700BT', 'ATH-SR30BT', 'ATH-CKS30', 'ATH-AD700X', 'ATH-GDL3', 'ATH-MSR7b']],
            ['Sennheiser',  ['Momentum 4', 'Momentum True Wireless 3', 'HD 560S', 'HD 660S2', 'HD 600', 'IE 600', 'CX Plus', 'Momentum 3', 'PXC 550-II', 'HD 280 Pro']],
            ['Skullcandy',  ['Crusher ANC 2', 'Hesh ANC', 'Push Active', 'Dime 3', 'Sesh Evo', 'Grind Fuel', 'Crusher Evo', 'Push Ultra', 'Ink\'d Plus']],
            ['Edifier',     ['W820NB Plus', 'W820NB', 'W830BT', 'Stax Spirit S3', 'Neobuds Pro 2', 'NeoBuds Pro', 'TWS1 Pro2', 'H840', 'G2 II']],
            ['Philips',     ['TAH4205', 'TAN4205', 'SHL5305', 'TAPH805', 'Philips Sports TAA7607', 'PH802', 'TAUH205', 'SHB2505']],
            ['Anker',       ['Soundcore Liberty 4', 'Soundcore Life Q30', 'Soundcore Spirit X2', 'Soundcore Motion+', 'Soundcore Liberty Air 2 Pro', 'Soundcore Space Q45', 'Soundcore AnkerLife Q20+', 'Soundcore Frames']],
        ],

        'Mouses' => [
            ['Logitech',     ['G Pro X Superlight 2', 'G Pro X Superlight', 'G502 X', 'G502 Hero', 'G305', 'MX Master 3S', 'MX Anywhere 3S', 'G703', 'G403 Hero', 'G102', 'MX Vertical', 'ERGO M575', 'Pebble 2', 'Lift', 'MX Keys Mini', 'G903']],
            ['Razer',        ['Viper V3 HyperSpeed', 'Viper V2 Pro', 'Viper V3 Pro', 'DeathAdder V3', 'DeathAdder V3 Pro', 'Basilisk V3 Pro', 'Basilisk V3', 'Cobra Pro', 'Viper Mini', 'DeathAdder Essential', 'Naga V2 Pro', 'Naga V2 HyperSpeed']],
            ['Corsair',     ['M75 Air', 'M75', 'DARK CORE RGB Pro', 'SCIMITAR RGB Elite', 'KATAR ELITE', 'SABRE RGB Pro', 'HS65', 'M65 Ultra', 'IRONCLAW', 'SCIMITAR']],
            ['SteelSeries', ['Prime Wireless', 'Prime', 'Rival 3', 'Rival 5', 'Sensei Ten', 'Sensei 3XL', 'Rival 600', 'Aerox 5 Wireless', 'Aerox 9 Wireless', 'Prime Mini']],
            ['HP',          ['HyperX Pulsefire Haste 2', 'HyperX Pulsefire Haste', 'HyperX Pulsefire Core', 'HP WF100', 'HP FM100', 'HyperX Duo Cast', 'HP 220']],
            ['Dell',        ['MS3320W', 'MS3320', 'MS5320W', 'MS5320', 'KM7120W', 'MS116', 'WM525', 'DW316', 'DELL MS5120']],
            ['Microsoft',   ['Pro IntelliMouse', 'Classic IntelliMouse', 'Wireless Mouse 2000', 'Bluetooth Mouse 4000', 'Arc Mouse', 'Arc Touch', 'Sculpt Ergonomic', 'Wireless Mobile 4000']],
            ['Apple',       ['Magic Mouse', 'Magic Mouse 2', 'Magic Trackpad']],
            ['A4Tech',      ['Bloody A70', 'Bloody A90', 'Bloody TL80', 'Bloody V9M', 'X7-500MD', 'F75', 'A90 Pro', 'Bloody P93']],
            ['Bloody',      ['A70', 'A90', 'A98', 'V9M', 'TL80', 'P93']],
            ['HyperX',      ['Pulsefire Haste 2', 'Pulsefire Haste', 'Pulsefire Core', 'Pulsefire Raid', 'Pulsefire Surge', 'Pulsefire FPS Pro', 'Dart', 'Haste Wireless']],
            ['Kingston',    ['HyperX Pulsefire Haste', 'HyperX Pulsefire Core', 'HyperX Pulsefire FPS Pro']],
            ['Trust',       ['GXT 108R', 'GXT 133', 'GXT 165', 'GXT 922', 'GXT 20', 'GXT 117', 'GXT 108']],
            ['Rapoo',       ['VT9 Pro', 'V330', 'V25S', 'MT760', 'G1020', 'VT200', 'MT550']],
        ],

        'Teclados' => [
            ['Logitech',     ['G Pro X TKL', 'G Pro X', 'G915 TKL', 'G915', 'G815', 'MX Keys S', 'MX Keys Mini', 'K380', 'K480', 'Craft', 'G413', 'ERGO K860', 'Pop Keys', 'K580', 'MK470', 'MK850']],
            ['Razer',        ['Huntsman V3 Pro', 'Huntsman V3', 'Huntsman Mini', 'Huntsman Elite', 'BlackWidow V4', 'BlackWidow V3 Pro', 'BlackWidow V3', 'BlackWidow V4 Pro', 'Ornata V3', 'Cynosa V2', 'Ornata Chroma', 'DeathStalker V2 Pro']],
            ['Corsair',     ['K100 RGB', 'K70 RGB Pro', 'K70 Max', 'K65 Plus', 'K60 Pro', 'K55 RGB Pro', 'K83', 'K100 Air', 'K57 RGB', 'K65 RGB Mini', 'K68', 'Strafe RGB']],
            ['HyperX',      ['Alloy Origins Core', 'Alloy Origins', 'Alloy Origins PBT', 'Alloy Elite 2', 'Alloy FPS RGB', 'Alloy MKW100', 'Clutch Core']],
            ['SteelSeries', ['Apex Pro TKL', 'Apex Pro', 'Apex 7 TKL', 'Apex 7', 'Apex 5', 'Apex 3 TKL', 'Apex 3', 'Apex 9 Mini', 'Apex 9 TKL', 'Apex 9']],
            ['HP',          ['HyperX Alloy FPS Pro', 'HP G210', 'HP GK400', 'HP 150', 'HyperX Alloy Core', 'HP USB Slim', 'HP USB Classic']],
            ['Dell',        ['KB216', 'KB212', 'KM113', 'DELL KM5221W', 'Pro Plus KB', 'Multi-Device', 'Smart Wireless']],
            ['Microsoft',   ['Surface Keyboard', 'Surface Ergonomic', 'Ergonomic Keyboard 4000', 'Wireless Keyboard 850', 'Bluetooth Ergonomic', 'Designer Compact', 'Universal Foldable']],
            ['Apple',       ['Magic Keyboard', 'Magic Keyboard with Touch ID', 'Magic Keyboard with Numeric Keypad', 'Smart Keyboard Folio', 'Magic Keyboard (USB-C)']],
            ['Redragon',    ['K552 Kumara', 'K530 Draconic', 'K599 Deimos', 'K617 FIZZ', 'K614 Alice', 'K582 SURARA', 'K673 PRO']],
            ['Genius',      ['GX Gaming Scorpion', 'KB-G265', 'K95', 'SlimStar I220', 'Gx Twist', 'KB-101']],
            ['A4Tech',      ['B2262', 'B3810', 'G800V', 'WK-100', 'B7500', 'LK-185']],
        ],

        'Impresoras' => [
            ['HP',     ['LaserJet Pro M404dn', 'LaserJet Pro M404n', 'LaserJet Pro MFP M428fdn', 'LaserJet Pro MFP M428fdw', 'LaserJet MFP M232dwc', 'Color LaserJet Pro MFP M283fdw', 'OfficeJet Pro 9010', 'OfficeJet Pro 9015e', 'OfficeJet Pro 9125e', 'Smart Tank 5108', 'Smart Tank 5200', 'DeskJet 2855e', 'DeskJet 4255e', 'Envoy 5100', 'LaserJet M110w', 'LaserJet MFP M234dw']],
            ['Canon', ['imageCLASS MF453dw', 'imageCLASS MF451dw', 'imageCLASS MF269dw', 'PIXMA G3411', 'PIXMA G3420', 'PIXMA G6020', 'PIXMA TS3720', 'PIXMA TR4720', 'PIXMA TR8620a', 'imageCLASS LBP623Cdw', 'MAXIFY GX7021', 'PIXMA MegaTank G3520']],
            ['Epson', ['EcoTank L3250', 'EcoTank L3260', 'EcoTank L5290', 'EcoTank ET-2850', 'EcoTank ET-3850', 'EcoTank ET-4850', 'WorkForce WF-2960', 'WorkForce WF-3820', 'WorkForce Pro WF-4830', 'WorkForce Pro WF-4835', 'SureColor T3170', 'EcoTank M15280']],
            ['Brother', ['HL-L2370DW', 'HL-L2350DW', 'HL-L3280CDW', 'HL-L3220CDW', 'MFC-J4335DW', 'MFC-J1205W', 'MFC-L3780CDW', 'MFC-L3720CDW', 'MFC-L8390CDW', 'DCP-T427W', 'DCP-L2550DW', 'MFC-L2750DW']],
            ['Samsung', ['Xpress SL-M2020W', 'Xpress SL-M2070FW', 'Xpress SL-M2835DW', 'Xpress SL-M4025ND', 'Xpress SL-M2885FW', 'ProXpress SL-M3015DW', 'CLP-365W', 'CLX-3305FW']],
            ['Lexmark', ['MS421dw', 'MB2442adwe', 'B2236dw', 'B2338dw', 'B3442dw', 'MC3326i', 'MC3426i', 'MS521dn', 'MX431adn']],
            ['Kyocera', ['ECOSYS P2040dn', 'ECOSYS P2235dn', 'ECOSYS M2040dn', 'ECOSYS M2540dn', 'ECOSYS M5526cdw', 'ECOSYS P5026cdw', 'TASKalfa 2554ci']],
            ['Xerox',   ['B210', 'B215', 'B205', 'C310', 'C315', 'C400', 'Phaser 3330', 'WorkCentre 3335', 'VersaLink C405']],
            ['Ricoh',   ['SP 150W', 'SP C260SFNw', 'SP C261SFNw', 'IM C300', 'IM C4000', 'MP 2554', 'MP C3003']],
        ],

        'Toners' => [
            ['HP',     ['CF226A (MFP M225)', 'CF228A (M402)', 'CF280A (P2055)', 'CF283A (M1536)', 'CF400A (Color MFP)', 'CF410A (Color LaserJet)', 'CE410A (Color)', 'CE505A (P2055)', 'CE390A (M601)', 'CF230A (MFP M232)', 'CF256A (MFP M283)', 'CF360A (MFP M281)', 'W1102A (M110)', 'W1370A (MFP M234)', 'CF259A (MFP M428)']],
            ['Canon', ['051H (MF269)', '051 (MF269)', '055H (MF453)', '055 (MF453)', '124 (MF441)', '124H (MF441)', '125 (MF477)', '125H (MF477)']],
            ['Brother', ['TN-2320 (HL-L2350)', 'TN-2380 (HL-L2370)', 'TN-830 (HL-L2310)', 'TN-2420 (HL-L2370DW)', 'TN-3480 (HL-L3220)', 'TN-2410 (HL-L2350DW)', 'TN-880 (MFC-L8390)', 'TN-3430 (HL-L3280)']],
            ['Samsung', ['MLT-D111S (M2020)', 'MLT-D111L (M2070)', 'MLT-D1052 (M2885)', 'MLT-D205E (M2835)', 'MLT-K1612S (M2885FW)', 'CLT-P404C (CLP-365)']],
            ['Lexmark', ['56F2H00 (MS421)', '56F2Z00 (MS421)', '56F2200 (MB2442)', '55F2H00 (B2236)', '55D2H00 (B2338)', '58D0H00 (MC3326)']],
            ['Xerox',   ['006R04390 (B210)', '006R04388 (B215)', '006R04391 (C310)', '006R04393 (C400)', '106R03610 (Phaser 3330)', '106R03611 (Phaser 3330)']],
            ['Kyocera', ['TK-120 (ECOSYS P2040)', 'TK-130 (ECOSYS P2235)', 'TK-5234 (ECOSYS P5026)', 'TK-5334 (ECOSYS M5526)']],
            ['Ricoh',   ['SP 111 (SP 150W)', 'SP 310CE (SP C260)', 'SP 3610HA (IM C300)']],
            ['Epson',   ['003 (EcoTank L3250)', '003 XL (EcoTank)', '012 (EcoTank L5290)', '012 XL (EcoTank)']],
        ],

        'Cartuchos de Tinta' => [
            ['HP',     ['61 (Negro)', '61 (Color)', '61XL (Negro)', '65 (Negro)', '65 (Color)', '65XL (Negro)', '65XL (Color)', '67 (Negro)', '67 (Color)', '67XL (Negro)', '305 (Negro)', '305 (Color)', '305XL (Negro)', '952 (Negro)', '952XL (Color)', '951 (Negro)', '951 (Color)', '934XL (Negro)']],
            ['Canon', ['PG-245', 'CL-246', 'PG-245XL', 'CL-246XL', 'PG-260', 'CL-261', 'PG-280', 'CL-281', 'PG-280XL', 'CL-281XL', 'PG-375', 'CL-376', 'CLI-281', 'PGI-280']],
            ['Epson', ['112 (Negro)', '112 (Cyan)', '112 (Magenta)', '112 (Yellow)', '114 (Negro)', '114 (Color)', '212 (Negro)', '212 (Color)', '272 (Negro)', '272 (Color)', '401 (Negro)', '401 (Color)', '802 (Negro)', '802XL (Color)']],
            ['Brother', ['LC3013 (Negro)', 'LC3013 (Cyan)', 'LC3013 (Magenta)', 'LC3013 (Yellow)', 'LC3017 (Negro)', 'LC3017 (Color)', 'LC401S (Negro)', 'LC401BK', 'LC404']],
        ],

        'Notebooks' => [
            ['Dell',     ['Latitude 3440', 'Latitude 5540', 'Latitude 7440', 'Latitude 9440', 'Inspiron 15', 'Inspiron 14', 'Inspiron 16', 'XPS 13', 'XPS 13 Plus', 'XPS 15', 'XPS 16', 'Vostro 3520', 'Vostro 5430', 'G15 5530', 'Alienware m16', 'Alienware x14']],
            ['HP',       ['ProBook 450 G10', 'ProBook 440 G10', 'ProBook 650 G10', 'EliteBook 840 G10', 'EliteBook 860 G10', 'Pavilion 15', 'Pavilion 14', 'Envy x360 15', 'Envy 16', 'Spectre x360 16', 'Victus 15', 'Omen 16', 'Omen 17', 'Chromebook 14']],
            ['Lenovo',   ['ThinkPad T14s', 'ThinkPad T14', 'ThinkPad T16', 'ThinkPad L14', 'ThinkPad L16', 'ThinkPad X1 Carbon', 'ThinkPad X1 Yoga', 'ThinkPad X13', 'IdeaPad 3 15', 'IdeaPad 5 14', 'IdeaPad Slim 3', 'Legion Pro 5', 'Legion Pro 7', 'Legion 5', 'Legion Slim 7', 'Yoga 9i']],
            ['ASUS',     ['VivoBook 15', 'VivoBook 14', 'VivoBook S 14', 'VivoBook S 15', 'ZenBook 14', 'ZenBook 13', 'ZenBook S 13', 'ROG Strix G16', 'ROG Strix G18', 'ROG Zephyrus G14', 'ROG Zephyrus G16', 'ROG Zephyrus M16', 'TUF Gaming A15', 'TUF Gaming F15', 'ExpertBook B1', 'ProArt Studiobook']],
            ['Acer',     ['Aspire 3', 'Aspire 5', 'Aspire 7', 'Aspire Go 14', 'Swift Go 14', 'Swift X', 'Spin 5', 'Swift 3', 'Nitro V 15', 'Nitro 5', 'Nitro 16', 'Predator Helios 16', 'Predator Helios 18', 'Predator Triton 14', 'TravelMate P2', 'Enduro N3']],
            ['MSI',      ['Modern 14', 'Modern 15', 'Prestige 14', 'Prestige 16', 'Creator Z16', 'Creator M16', 'Thin GF63', 'Katana 15', 'Pulse 16', 'Stealth 16 Studio', 'Raider GE78 HX', 'Raider GE68 HX', 'Crosshair 16', 'Bravo 15']],
            ['Apple',    ['MacBook Air M3 13"', 'MacBook Air M3 15"', 'MacBook Air M2 13"', 'MacBook Pro M3 14"', 'MacBook Pro M3 Pro 14"', 'MacBook Pro M3 Max 16"', 'MacBook Pro M2 Pro 13"', 'MacBook Pro M2 Max 16"']],
            ['Samsung',  ['Galaxy Book3', 'Galaxy Book3 Pro', 'Galaxy Book3 360', 'Galaxy Book4 Pro', 'Galaxy Book4 360', 'Galaxy Book4 Ultra', 'Galaxy Book2 Pro 360']],
            ['Huawei',   ['MateBook D 15', 'MateBook D 16', 'MateBook 14', 'MateBook 14s', 'MateBook X Pro', 'MateBook B5-430']],
            ['Toshiba',  ['Satellite Pro C50', 'Tecra A50', 'Dynabook Satellite Pro C40', 'Dynabook Tecra A40']],
            ['Compaq',   ['Presario 14', 'Presario 15', 'Presario CQ15']],
            ['LG',       ['Gram 14', 'Gram 15', 'Gram 16', 'Gram 17', 'Gram 2-in-1 14', 'Ultra PC 15']],
        ],
    ];
}

// =========================================================================
// Generación de nombres únicos
// =========================================================================

$catalog = catalogo();
$items   = []; // [[nombre, precio, categoria], ...]

foreach ($catalog as $categoria => $marcas) {
    foreach ($marcas as [$marca, $modelos]) {
        foreach ($modelos as $modelo) {
            $nombre = $categoria . ' ' . $marca . ' ' . $modelo;
            $precio = generarPrecio($categoria);

            $items[] = [$nombre, $precio, $categoria];
        }
    }
}

// Mezclar para que no queden todos agrupados por categoría.
shuffle($items);

// Si el catálogo supera la cantidad pedida, recortar.
$items = array_slice($items, 0, $cantidad);

// =========================================================================
// Categorias: sembrar las 8 y mapear nombre -> id para la FK de cada item
// =========================================================================

$categoriaIds = []; // ['Monitores' => 1, ...]
foreach (array_keys($catalog) as $catNombre) {
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO categorias (nombre) VALUES (:nombre)');
    $stmt->execute([':nombre' => $catNombre]);

    $select = $pdo->prepare('SELECT id FROM categorias WHERE nombre = :nombre');
    $select->execute([':nombre' => $catNombre]);
    $categoriaIds[$catNombre] = (int) $select->fetchColumn();
}

// Asegurar unicidad (case-insensitive), como exige la regla de negocio.
$seen  = [];
$final = [];
foreach ($items as [$nombre, $precio, $categoria]) {
    $key = mb_strtolower(trim($nombre));
    if (isset($seen[$key])) {
        continue; // saltar duplicado
    }
    $seen[$key] = true;
    $final[]    = [$nombre, $precio, $categoria];
}

// Rellenar con variaciones genéricas hasta llegar exactamente a la cantidad.
// "array_rand" sobre un array con claves de texto devuelve LA CLAVE, no un
// índice; por eso se usa $cats[$idx % count($cats)] para iterar categorías.
$cats = array_keys($catalog);
$idx  = 1;
while (count($final) < $cantidad) {
    $cat    = $cats[$idx % count($cats)];
    $nombre = $cat . ' Generico Modelo ' . str_pad((string) $idx, 4, '0', STR_PAD_LEFT);
    $key    = mb_strtolower($nombre);
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $final[]    = [$nombre, generarPrecio($cat), $cat];
    }
    $idx++;
}

// Ordenar alfabéticamente (como lo hace el repo: ORDER BY id)
// Mantenemos el orden de inserción; el frontend mostrará los items.

// =========================================================================
// Insertar en la base de datos
// =========================================================================

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('INSERT INTO items (nombre, precio, categoria_id) VALUES (:nombre, :precio, :categoria_id)');

    $count = 0;
    foreach ($final as [$nombre, $precio, $categoria]) {
        $insert->execute([
            ':nombre'       => $nombre,
            ':precio'       => $precio,
            ':categoria_id' => $categoriaIds[$categoria] ?? null,
        ]);
        $count++;
    }

    $pdo->commit();
    echo "✅ {$count} items insertados correctamente.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "❌ Error: " . $e->getMessage() . "\n");
    exit(1);
}

// =========================================================================
// Funciones auxiliares
// =========================================================================

function generarPrecio(string $categoria): float
{
    $rangos = [
        'Monitores'           => [80.00, 500.00],
        'Auriculares'         => [5.00,  300.00],
        'Mouses'              => [5.00,  100.00],
        'Teclados'            => [8.00,  200.00],
        'Impresoras'          => [50.00, 800.00],
        'Toners'              => [20.00, 150.00],
        'Cartuchos de Tinta'  => [15.00, 80.00],
        'Notebooks'           => [300.00, 2000.00],
    ];

    [$min, $max] = $rangos[$categoria] ?? [10.00, 100.00];

    // Precio con 2 decimales, distribución realista (ponderada hacia abajo)
    $raw = $min + ($max - $min) * (pow(mt_rand() / mt_getrandmax(), 1.5));

    return round($raw, 2);
}