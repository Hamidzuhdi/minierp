<?php
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php'; // mPDF autoload

// Pastikan user sudah login dan role Owner
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$user_role = $_SESSION['role'] ?? 'Admin';
if ($user_role !== 'Owner') {
    die('Hanya Owner yang dapat mengakses invoice PDF');
}

$spk_id = (int)$_GET['spk_id'];

// Get SPK data
$sql = "SELECT s.*, 
        c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
        v.nomor_polisi, v.merk, v.model, v.tahun
        FROM spk s
        JOIN customers c ON s.customer_id = c.id
        JOIN vehicles v ON s.vehicle_id = v.id
        WHERE s.id = $spk_id";

$result = mysqli_query($conn, $sql);

if (!$row = mysqli_fetch_assoc($result)) {
    die('SPK tidak ditemukan');
}

$spk = $row;

// Get SPK items (sparepart) dengan harga jual
$sql_items = "SELECT si.*, sp.nama as sparepart_name, sp.satuan, sp.harga_jual_default,
              (si.qty * sp.harga_jual_default) as subtotal_jual
              FROM spk_items si
              JOIN spareparts sp ON si.sparepart_id = sp.id
              WHERE si.spk_id = $spk_id";
$result_items = mysqli_query($conn, $sql_items);

$items = [];
$total_sparepart = 0;
while ($item = mysqli_fetch_assoc($result_items)) {
    $items[] = $item;
    $total_sparepart += $item['subtotal_jual'];
}

// Get SPK services (jasa service)
$sql_services = "SELECT ss.*, sp.nama_jasa, sp.kategori
                 FROM spk_services ss
                 JOIN service_prices sp ON ss.service_price_id = sp.id
                 WHERE ss.spk_id = $spk_id";
$result_services = mysqli_query($conn, $sql_services);

$services = [];
$total_jasa = 0;
while ($svc = mysqli_fetch_assoc($result_services)) {
    $services[] = $svc;
    $total_jasa += $svc['subtotal'];
}

$grand_total = $total_sparepart + $total_jasa;

// Generate filename: namacust_nopol_mobil_tglsekarang
$customer_name_clean = preg_replace('/[^a-zA-Z0-9]/', '_', $spk['customer_name']);
$nopol_clean = preg_replace('/[^a-zA-Z0-9]/', '_', $spk['nomor_polisi']);
$jenis_mobil_clean = preg_replace('/[^a-zA-Z0-9]/', '_', $spk['merk'] . '_' . $spk['model']);
$tanggal_clean = date('Ymd'); // Tanggal sekarang
$filename = $customer_name_clean . '_' . $nopol_clean . '_' . $jenis_mobil_clean . '_' . $tanggal_clean . '.pdf';

// Initialize mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 20,
    'margin_bottom' => 30,
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_header' => 10,
    'margin_footer' => 10
]);

// Set document properties
$mpdf->SetTitle('Invoice ' . $spk['kode_unik_reference']);
$mpdf->SetAuthor('Bengkel Mini ERP');

// Set footer with page numbering - center aligned
$mpdf->SetFooter('{PAGENO}');

// Start output buffering to capture HTML
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $spk['kode_unik_reference']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section table {
            width: 100%;
        }
        .info-section td {
            padding: 3px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .items-table .grand-total {
            background-color: #f0f0f0;
            font-weight: bold;
            border-top: 2px solid #000;
        }
        .items-table .text-right {
            text-align: right;
        }
        .items-table .text-center {
            text-align: center;
        }
        .total-section {
            margin-top: 20px;
            float: right;
            width: 300px;
        }
        .total-section table {
            width: 100%;
            border-collapse: collapse;
        }
        .total-section td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        .total-section .grand-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #000;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>BENGKEL MINI ERP</h1>
        <p>Jl. Contoh No. 123, Jakarta</p>
        <p>Telp: (021) 1234-5678 | Email: info@bengkel.com</p>
    </div>

    <div class="info-section">
        <table>
            <tr>
                <td width="15%"><strong>Invoice No:</strong></td>
                <td width="35%"><?php echo $spk['kode_unik_reference']; ?></td>
                <td width="15%"><strong>Tanggal:</strong></td>
                <td width="35%"><?php echo date('d/m/Y', strtotime($spk['tanggal'])); ?></td>
            </tr>
            <tr>
                <td><strong>Customer:</strong></td>
                <td><?php echo $spk['customer_name']; ?></td>
                <td><strong>Telepon:</strong></td>
                <td><?php echo $spk['customer_phone']; ?></td>
            </tr>
            <tr>
                <td><strong>Alamat:</strong></td>
                <td colspan="3"><?php echo $spk['customer_address'] ?? '-'; ?></td>
            </tr>
            <tr>
                <td><strong>Kendaraan:</strong></td>
                <td colspan="3"><?php echo $spk['nomor_polisi'] . ' - ' . $spk['merk'] . ' ' . $spk['model'] . ' (' . $spk['tahun'] . ')'; ?></td>
            </tr>
            <tr>
                <td><strong>Keluhan:</strong></td>
                <td colspan="3"><?php echo $spk['keluhan_customer']; ?></td>
            </tr>
        </table>
    </div>

    <h3>Detail Pekerjaan Jasa Service</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" width="5%">No</th>
                <th width="50%">Nama Jasa</th>
                <th class="text-center" width="10%">Qty</th>
                <th class="text-right" width="15%">Harga</th>
                <th class="text-right" width="20%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            if (count($services) > 0) {
                foreach ($services as $svc): 
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td><?php echo $svc['nama_jasa']; ?></td>
                <td class="text-center"><?php echo $svc['qty']; ?></td>
                <td class="text-right">Rp <?php echo number_format($svc['harga'], 0, ',', '.'); ?></td>
                <td class="text-right">Rp <?php echo number_format($svc['subtotal'], 0, ',', '.'); ?></td>
            </tr>
            <?php 
                endforeach;
            } else {
            ?>
            <tr>
                <td colspan="5" class="text-center">Tidak ada jasa service</td>
            </tr>
            <?php } ?>
            <tr class="grand-total">
                <td colspan="4" class="text-right"><strong>Total Jasa Service:</strong></td>
                <td class="text-right"><strong>Rp <?php echo number_format($total_jasa, 0, ',', '.'); ?></strong></td>
            </tr>
        </tbody>
    </table>

    <h3 style="margin-top: 30px;">Detail Sparepart yang Digunakan</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" width="5%">No</th>
                <th width="40%">Nama Sparepart</th>
                <th class="text-center" width="10%">Qty</th>
                <th class="text-center" width="10%">Satuan</th>
                <th class="text-right" width="15%">Harga</th>
                <th class="text-right" width="20%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            if (count($items) > 0) {
                foreach ($items as $item): 
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td><?php echo $item['sparepart_name']; ?></td>
                <td class="text-center"><?php echo $item['qty']; ?></td>
                <td class="text-center"><?php echo $item['satuan']; ?></td>
                <td class="text-right">Rp <?php echo number_format($item['harga_jual_default'], 0, ',', '.'); ?></td>
                <td class="text-right">Rp <?php echo number_format($item['subtotal_jual'], 0, ',', '.'); ?></td>
            </tr>
            <?php 
                endforeach;
            } else {
            ?>
            <tr>
                <td colspan="6" class="text-center">Tidak ada sparepart</td>
            </tr>
            <?php } ?>
            <tr class="grand-total">
                <td colspan="5" class="text-right"><strong>Total Sparepart:</strong></td>
                <td class="text-right"><strong>Rp <?php echo number_format($total_sparepart, 0, ',', '.'); ?></strong></td>
            </tr>
        </tbody>
    </table>

    <div class="clearfix">
        <div class="total-section">
            <table>
                <tr>
                    <td><strong>Total Jasa Service:</strong></td>
                    <td class="text-right">Rp <?php echo number_format($total_jasa, 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Sparepart:</strong></td>
                    <td class="text-right">Rp <?php echo number_format($total_sparepart, 0, ',', '.'); ?></td>
                </tr>
                <tr class="grand-total">
                    <td><strong>GRAND TOTAL:</strong></td>
                    <td class="text-right"><strong>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></strong></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Saran Service - Always show -->
    <div style="clear: both; margin-top: 30px; border: 1px solid #000; padding: 15px; background-color: #f9f9f9;">
        <h4 style="margin-top: 0; margin-bottom: 10px;">Saran Service:</h4>
        <p style="margin: 0; min-height: 60px;"><?php echo !empty($spk['saran_service']) ? nl2br(htmlspecialchars($spk['saran_service'])) : '-'; ?></p>
    </div>

    <div style="clear: both; margin-top: 80px;">
        <table width="100%">
            <tr>
                <td width="50%" style="text-align: center;">
                    <p>Mengetahui,</p>
                    <br><br><br>
                    <p>____________________</p>
                    <p>Customer</p>
                </td>
                <td width="50%" style="text-align: center;">
                    <p>Hormat Kami,</p>
                    <br><br><br>
                    <p>____________________</p>
                    <p>Bengkel</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Terima kasih atas kepercayaan Anda | Printed: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

</body>
</html>
<?php
// Get the buffered HTML content
$html = ob_get_clean();

// Write HTML to mPDF
$mpdf->WriteHTML($html);

// Output PDF to browser
$mpdf->Output($filename, 'I');
?>
