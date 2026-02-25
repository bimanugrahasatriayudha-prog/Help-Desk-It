<?php
/**
 * Email Helper — PHPMailer via Brevo (Sendinblue) SMTP
 * ─────────────────────────────────────────────────────
 * Setup:
 *  1. Daftar / login di https://app.brevo.com
 *  2. Menu: Account → SMTP & API → SMTP tab
 *  3. Catat: Login (email), Master password / SMTP password
 *  4. SMTP host: smtp-relay.brevo.com, port: 587, encryption: TLS
 *  5. Isi BREVO_SMTP_USER dan BREVO_SMTP_PASSWORD di bawah
 * ─────────────────────────────────────────────────────
 */

// ══ KONFIGURASI BREVO SMTP — EDIT BAGIAN INI ════════════════
define('MAIL_HOST',       'smtp-relay.brevo.com');
define('MAIL_PORT',       587);
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_USERNAME',   'a34c0a001@smtp-brevo.com');  // ← login Brevo kamu
define('MAIL_PASSWORD',   'gnjETxYqRkWrMm4y');
define('MAIL_FROM_EMAIL', 'tsktsh5@gmail.com');
define('MAIL_FROM_NAME',  'Helpdesk IT NSC Surabaya');
// ════════════════════════════════════════════════════════════

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Kirim email generik
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody): array {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $htmlBody));
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log('[Helpdesk Email] PHPMailer Error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Email konfirmasi tiket baru ke user
 */
function sendTicketEmail(string $toEmail, string $toName, string $kode, string $judul, string $chatToken, string $baseUrl): array {
    $chatLink  = rtrim($baseUrl, '/') . '/chat?token=' . $chatToken;
    $subject   = "Tiket Helpdesk IT Berhasil Dibuat — {$kode}";
    $yr        = date('Y');
    $nama_esc  = htmlspecialchars($toName,  ENT_QUOTES);
    $kode_esc  = htmlspecialchars($kode,    ENT_QUOTES);
    $judul_esc = htmlspecialchars($judul,   ENT_QUOTES);
    $link_esc  = htmlspecialchars($chatLink, ENT_QUOTES);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Segoe UI,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f0f4f8" style="padding:30px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0"
       style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)">
  <tr>
    <td style="background:linear-gradient(135deg,#002855,#003d7a,#1565c0);padding:30px 36px;text-align:center">
      <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj"
           width="64" height="64" style="border-radius:50%;border:3px solid #e8a020;display:block;margin:0 auto 14px">
      <div style="color:#fff;font-size:18px;font-weight:700">Politeknik NSC Surabaya</div>
      <div style="color:rgba(255,255,255,.65);font-size:13px;margin-top:4px">Helpdesk IT Terpadu</div>
    </td>
  </tr>
  <tr>
    <td style="padding:30px 36px">
      <p style="margin:0 0 6px;color:#1a1a2e;font-size:16px;font-weight:600">Halo, {$nama_esc}!</p>
      <p style="margin:0 0 22px;color:#6b7280;font-size:14px;line-height:1.7">
        Laporan Anda telah berhasil diterima tim Helpdesk IT. Berikut detail tiket Anda:
      </p>
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;margin-bottom:24px">
        <tr><td style="padding:18px 22px">
          <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px">
            <tr>
              <td style="color:#6b7280;padding:5px 0;width:140px">Kode Tiket</td>
              <td style="color:#003d7a;font-weight:700;font-size:14px">{$kode_esc}</td>
            </tr>
            <tr>
              <td style="color:#6b7280;padding:5px 0">Judul Laporan</td>
              <td style="color:#1a1a2e">{$judul_esc}</td>
            </tr>
            <tr>
              <td style="color:#6b7280;padding:5px 0">Status</td>
              <td><span style="background:#dbeafe;color:#1d4ed8;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700">Open</span></td>
            </tr>
          </table>
        </td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px">
        <tr><td align="center">
          <a href="{$link_esc}"
             style="display:inline-block;background:#003d7a;color:#fff;text-decoration:none;
                    padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600">
            Buka Chat Tiket Saya
          </a>
        </td></tr>
      </table>
      <div style="background:#f7f9fc;border:1px solid #dde3ed;border-radius:6px;
                  padding:12px 16px;margin-bottom:18px;font-size:12px;color:#003d7a;word-break:break-all">
        {$link_esc}
      </div>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;
                  padding:12px 16px;font-size:12px;color:#92400e">
        ⚠️ Penting: Simpan email ini. Link di atas adalah satu-satunya akses ke tiket Anda.
        Jangan bagikan link kepada orang lain.
      </div>
    </td>
  </tr>
  <tr>
    <td style="background:#f7f9fc;padding:16px 36px;text-align:center;border-top:1px solid #dde3ed">
      <p style="margin:0;color:#9ca3af;font-size:11px;line-height:1.8">
        &copy; {$yr} Politeknik NSC Surabaya &mdash; Helpdesk IT<br>
        Email ini dikirim otomatis via Brevo. Mohon tidak membalas email ini.
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    return sendEmail($toEmail, $toName, $subject, $html);
}

/**
 * Email notifikasi ke user ketika admin membalas chat
 */
function sendReplyNotifEmail(string $toEmail, string $toName, string $kode, string $judul, string $chatToken, string $baseUrl, string $previewPesan = ''): array {
    $chatLink  = rtrim($baseUrl, '/') . '/chat?token=' . $chatToken;
    $subject   = "Admin Helpdesk IT telah membalas tiket Anda — {$kode}";
    $yr        = date('Y');
    $nama_esc  = htmlspecialchars($toName,       ENT_QUOTES);
    $kode_esc  = htmlspecialchars($kode,         ENT_QUOTES);
    $judul_esc = htmlspecialchars($judul,        ENT_QUOTES);
    $link_esc  = htmlspecialchars($chatLink,     ENT_QUOTES);
    $preview   = htmlspecialchars(substr(strip_tags($previewPesan), 0, 120) . (strlen($previewPesan) > 120 ? '...' : ''), ENT_QUOTES);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Segoe UI,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f0f4f8" style="padding:30px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0"
       style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)">
  <tr>
    <td style="background:linear-gradient(135deg,#002855,#003d7a,#1565c0);padding:30px 36px;text-align:center">
      <img src="https://yt3.googleusercontent.com/DRseHHOtu76abuCJtyxIU08bvAIzC72HYWdgPPuhv-xQjO7C1XAmeheAvL-h4nxVROQwgXSt=s900-c-k-c0x00ffffff-no-rj"
           width="64" height="64" style="border-radius:50%;border:3px solid #e8a020;display:block;margin:0 auto 14px">
      <div style="color:#fff;font-size:18px;font-weight:700">Politeknik NSC Surabaya</div>
      <div style="color:rgba(255,255,255,.65);font-size:13px;margin-top:4px">Helpdesk IT Terpadu</div>
    </td>
  </tr>
  <tr>
    <td style="padding:30px 36px">
      <p style="margin:0 0 6px;color:#1a1a2e;font-size:16px;font-weight:600">Halo, {$nama_esc}!</p>
      <p style="margin:0 0 18px;color:#6b7280;font-size:14px;line-height:1.7">
        Tim IT Helpdesk NSC telah membalas tiket Anda. Silakan buka halaman chat untuk melihat balasan lengkap.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;margin-bottom:18px">
        <tr><td style="padding:16px 20px">
          <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px">
            <tr>
              <td style="color:#6b7280;padding:4px 0;width:130px">Kode Tiket</td>
              <td style="color:#003d7a;font-weight:700">{$kode_esc}</td>
            </tr>
            <tr>
              <td style="color:#6b7280;padding:4px 0">Judul</td>
              <td style="color:#1a1a2e">{$judul_esc}</td>
            </tr>
          </table>
        </td></tr>
      </table>
      {$preview_block}
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px">
        <tr><td align="center">
          <a href="{$link_esc}"
             style="display:inline-block;background:#003d7a;color:#fff;text-decoration:none;
                    padding:14px 36px;border-radius:8px;font-size:15px;font-weight:600">
            💬 Buka Chat &amp; Balas
          </a>
        </td></tr>
      </table>
      <div style="background:#f7f9fc;border:1px solid #dde3ed;border-radius:6px;
                  padding:10px 14px;font-size:11px;color:#6b7280;word-break:break-all">
        {$link_esc}
      </div>
    </td>
  </tr>
  <tr>
    <td style="background:#f7f9fc;padding:16px 36px;text-align:center;border-top:1px solid #dde3ed">
      <p style="margin:0;color:#9ca3af;font-size:11px;line-height:1.8">
        &copy; {$yr} Politeknik NSC Surabaya &mdash; Helpdesk IT<br>
        Email ini dikirim otomatis via Brevo. Mohon tidak membalas email ini.
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    // Inject preview block
    $previewBlock = $preview
        ? "<div style=\"background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px 18px;margin-bottom:18px;font-size:13px;color:#166534\">
             <strong>Pesan dari Admin:</strong><br style=\"margin-bottom:6px\">
             <span style=\"color:#374151\">{$preview}</span>
           </div>"
        : '';
    $html = str_replace('{$preview_block}', $previewBlock, $html);

    return sendEmail($toEmail, $toName, $subject, $html);
}
