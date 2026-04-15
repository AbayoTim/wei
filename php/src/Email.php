<?php
declare(strict_types=1);

/**
 * Minimal SMTP mailer — no external dependencies.
 * Supports STARTTLS on port 587 and SSL on port 465.
 */
class Email
{
    // ── Shared styles ─────────────────────────────────────────────────────────
    private static string $css = '
        body,table,td,p,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
        body{margin:0;padding:0;background:#f0f2f0;font-family:Arial,Helvetica,sans-serif;color:#333}
        table{border-collapse:collapse}
        img{border:0;display:block}
        .wrap{max-width:620px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;
              box-shadow:0 2px 12px rgba(0,0,0,.08)}
        /* ── Header ── */
        .hdr{background:#1a6b3c;padding:0}
        .hdr-inner{padding:28px 32px 20px;text-align:center}
        .logo-circle{width:64px;height:64px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.4);
                     border-radius:50%;margin:0 auto 12px;display:table-cell;vertical-align:middle;text-align:center;
                     font-size:20px;font-weight:900;color:#fff;letter-spacing:2px;line-height:64px;width:64px}
        .hdr h1{margin:0 0 4px;font-size:20px;font-weight:700;color:#fff;letter-spacing:.5px}
        .hdr-sub{margin:0;font-size:12px;color:rgba(255,255,255,.75);letter-spacing:1px;text-transform:uppercase}
        /* Tanzania flag stripe */
        .flag-stripe{height:6px;background:linear-gradient(90deg,#1EB53A 25%,#000 25%,#000 38%,#FCD116 38%,#FCD116 62%,#000 62%,#000 75%,#00A3DD 75%)}
        /* ── Body ── */
        .body{padding:32px 32px 24px}
        .body h2{margin:0 0 16px;font-size:22px;color:#1a6b3c;font-weight:700}
        .body p{margin:0 0 14px;font-size:15px;line-height:1.65;color:#444}
        .body p:last-child{margin-bottom:0}
        /* ── Info box ── */
        .info-box{background:#f7faf8;border:1px solid #d4e8dc;border-left:4px solid #1a6b3c;
                  border-radius:0 6px 6px 0;padding:16px 20px;margin:20px 0}
        .info-box p{margin:5px 0;font-size:14px;color:#333}
        .info-box p strong{color:#1a6b3c}
        /* ── Status badge ── */
        .badge{display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;letter-spacing:.5px}
        .badge-pending{background:#fff3cd;color:#856404}
        .badge-approved{background:#d1e7dd;color:#0a5132}
        .badge-rejected{background:#f8d7da;color:#842029}
        /* ── Button ── */
        .btn-wrap{text-align:center;margin:24px 0 8px}
        .btn{display:inline-block;padding:13px 32px;background:#1a6b3c;color:#fff !important;
             text-decoration:none;border-radius:6px;font-size:15px;font-weight:700;letter-spacing:.3px}
        .btn:hover{background:#145730}
        .btn-danger{background:#c0392b}
        /* ── Reply box ── */
        .reply-box{background:#f7faf8;border-left:4px solid #1a6b3c;padding:16px 20px;
                   margin:20px 0;border-radius:0 6px 6px 0;font-size:14px;color:#333;line-height:1.6}
        /* ── Divider ── */
        .divider{border:none;border-top:1px solid #e8ede9;margin:20px 0}
        /* ── Footer ── */
        .ftr{background:#f7faf8;border-top:1px solid #e8ede9;padding:20px 32px;text-align:center}
        .ftr p{margin:4px 0;font-size:12px;color:#888}
        .ftr a{color:#1a6b3c;text-decoration:none}
        .ftr-links{margin:8px 0 4px}
        .ftr-links a{display:inline-block;margin:0 6px;font-size:12px;color:#1a6b3c}
        .unsub{margin-top:8px;font-size:11px;color:#bbb}
        .unsub a{color:#bbb}
    ';

    // ── Template builders ─────────────────────────────────────────────────────

    public static function subscriptionConfirmed(string $name, string $unsubUrl): array
    {
        $n = htmlspecialchars($name ?: 'there', ENT_QUOTES);
        return [
            'subject' => 'Welcome to the WEI Newsletter!',
            'html'    => self::wrap(
                heading:  "Welcome, $n! 🎉",
                body:     '<p>Thank you for joining the <strong>Women Empowerment Initiatives</strong> community. You are now subscribed to our newsletter!</p>
                           <p>You will receive our latest news, impact stories, event updates, and ways you can support women empowerment across Tanzania.</p>
                           <hr class="divider">
                           <p>Have questions? Reach us at <a href="mailto:info@wei.or.tz" style="color:#1a6b3c">info@wei.or.tz</a> or call <strong>+255 743 111 867</strong>.</p>',
                unsubUrl: $unsubUrl,
            ),
        ];
    }

    public static function contactReceived(string $firstName): array
    {
        $n = htmlspecialchars($firstName ?: 'there', ENT_QUOTES);
        return [
            'subject' => 'We Received Your Message — WEI Tanzania',
            'html'    => self::wrap(
                heading: "Hello, $n!",
                body:    '<p>Thank you for reaching out to <strong>Women Empowerment Initiatives</strong>. We have received your message and will respond within <strong>1–3 business days</strong>.</p>
                          <p>If your matter is urgent, please contact us directly:</p>
                          <div class="info-box">
                            <p><strong>Phone:</strong> +255 743 111 867</p>
                            <p><strong>Email:</strong> <a href="mailto:info@wei.or.tz" style="color:#1a6b3c">info@wei.or.tz</a></p>
                            <p><strong>Location:</strong> Dodoma — Makulu, Tanzania</p>
                          </div>',
            ),
        ];
    }

    public static function donationReceived(string $donorName, float|string $amount, string $currency, string $ref): array
    {
        $n   = htmlspecialchars($donorName, ENT_QUOTES);
        $amt = number_format((float) $amount, 2);
        $r   = htmlspecialchars($ref, ENT_QUOTES);
        $c   = htmlspecialchars($currency, ENT_QUOTES);
        return [
            'subject' => "Donation Received — Pending Verification | Ref: $ref",
            'html'    => self::wrap(
                heading: "Thank You, $n!",
                body:    "<p>We have received your donation and our team is currently <strong>verifying your payment receipt</strong>. You will receive a confirmation once the verification is complete.</p>
                          <div class=\"info-box\">
                            <p><strong>Reference Number:</strong> $r</p>
                            <p><strong>Amount:</strong> $c $amt</p>
                            <p><strong>Status:</strong> <span class=\"badge badge-pending\">Pending Verification</span></p>
                          </div>
                          <p>Please keep your reference number for your records. If you have questions, contact us quoting this reference.</p>",
            ),
        ];
    }

    public static function donationApproved(string $donorName, float|string $amount, string $currency, string $ref): array
    {
        $n   = htmlspecialchars($donorName, ENT_QUOTES);
        $amt = number_format((float) $amount, 2);
        $r   = htmlspecialchars($ref, ENT_QUOTES);
        $c   = htmlspecialchars($currency, ENT_QUOTES);
        return [
            'subject' => "Donation Confirmed — Thank You! | Ref: $ref",
            'html'    => self::wrap(
                heading: "Your Donation is Confirmed! ✓",
                body:    "<p>Dear <strong>$n</strong>, your generous donation has been <strong>verified and confirmed</strong>. Your support makes a real, lasting difference in the lives of women and communities across Tanzania.</p>
                          <div class=\"info-box\">
                            <p><strong>Reference Number:</strong> $r</p>
                            <p><strong>Amount:</strong> $c $amt</p>
                            <p><strong>Status:</strong> <span class=\"badge badge-approved\">&#10003; Confirmed</span></p>
                          </div>
                          <p>On behalf of every woman whose life your donation will touch — <strong>Asante sana (Thank you so much)</strong>!</p>",
            ),
        ];
    }

    public static function donationRejected(string $donorName, string $ref, ?string $reason): array
    {
        $n   = htmlspecialchars($donorName, ENT_QUOTES);
        $r   = htmlspecialchars($ref, ENT_QUOTES);
        $rs  = htmlspecialchars($reason ?: 'Unable to verify payment receipt', ENT_QUOTES);
        return [
            'subject' => "Donation Verification Issue | Ref: $ref",
            'html'    => self::wrap(
                heading:   "Action Required — Donation Verification",
                body:      "<p>Dear <strong>$n</strong>, we were unable to verify your donation submission. Please see the details below.</p>
                            <div class=\"info-box\">
                              <p><strong>Reference Number:</strong> $r</p>
                              <p><strong>Status:</strong> <span class=\"badge badge-rejected\">Unable to Verify</span></p>
                              <p><strong>Reason:</strong> $rs</p>
                            </div>
                            <p>Please contact us with your receipt and reference number so we can resolve this promptly.</p>
                            <div class=\"btn-wrap\"><a href=\"mailto:info@wei.or.tz?subject=Donation%20Issue%20$r\" class=\"btn btn-danger\">Contact Us</a></div>",
                headerBg: '#c0392b',
            ),
        ];
    }

    public static function contactReplied(string $firstName, string $replyMessage): array
    {
        $n   = htmlspecialchars($firstName ?: 'there', ENT_QUOTES);
        $msg = nl2br(htmlspecialchars($replyMessage, ENT_QUOTES));
        return [
            'subject' => 'Response to Your Message — WEI Tanzania',
            'html'    => self::wrap(
                heading: "Hello, $n!",
                body:    "<p>Thank you for contacting <strong>Women Empowerment Initiatives</strong>. We have reviewed your message and have a response for you:</p>
                          <div class=\"reply-box\">$msg</div>
                          <p>If you have further questions or need additional assistance, please don't hesitate to get in touch.</p>
                          <div class=\"btn-wrap\"><a href=\"mailto:info@wei.or.tz\" class=\"btn\">Reply to Us</a></div>",
            ),
        ];
    }

    public static function adminNotification(string $type, array $details): array
    {
        $t    = htmlspecialchars($type, ENT_QUOTES);
        $rows = '';
        foreach ($details as $k => $v) {
            $rows .= '<p><strong>' . htmlspecialchars((string)$k, ENT_QUOTES) . ':</strong> '
                   . htmlspecialchars((string)$v, ENT_QUOTES) . '</p>';
        }
        $adminUrl = htmlspecialchars(FRONTEND_URL . '/admin', ENT_QUOTES);
        return [
            'subject' => "[WEI Admin] New $type",
            'html'    => self::wrap(
                heading:  "New $t",
                body:     "<div class=\"info-box\">$rows</div>
                           <div class=\"btn-wrap\"><a href=\"$adminUrl\" class=\"btn\">Open Admin Panel</a></div>",
                headerBg: '#1a3a6b',
            ),
        ];
    }

    // ── Send helpers ──────────────────────────────────────────────────────────

    public static function send(string $to, string $template, array $args): bool
    {
        if (!method_exists(self::class, $template)) {
            error_log("Email: unknown template '$template'");
            return false;
        }
        $tpl = self::$template(...$args);
        return self::smtp($to, $tpl['subject'], $tpl['html']);
    }

    public static function notifyAdmin(string $type, array $details): bool
    {
        $tpl = self::adminNotification($type, $details);
        return self::smtp(ADMIN_EMAIL, $tpl['subject'], $tpl['html']);
    }

    // ── SMTP sender ───────────────────────────────────────────────────────────

    private static function smtp(string $to, string $subject, string $html): bool
    {
        try {
            $host = SMTP_HOST;
            $port = SMTP_PORT;
            $user = SMTP_USER;
            $pass = SMTP_PASS;
            $from = EMAIL_FROM;

            if (empty($user) || empty($pass)) {
                error_log("Email: SMTP credentials not configured — skipping '$subject'");
                return false;
            }

            $ssl    = ($port === 465);
            $target = ($ssl ? 'ssl://' : '') . $host;

            $conn = @fsockopen($target, $port, $errno, $errstr, 10);
            if (!$conn) {
                error_log("Email: fsockopen failed — $errno $errstr");
                return false;
            }

            $read = fn() => fgets($conn, 512);
            $cmd  = function (string $c) use ($conn, $read): string {
                fwrite($conn, $c . "\r\n");
                return $read();
            };

            $read(); // greeting

            if (!$ssl) {
                $cmd('EHLO ' . gethostname());
                $cmd('STARTTLS');
                @stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }

            $cmd('EHLO ' . gethostname());
            $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $resp = $cmd(base64_encode($pass));
            if (!str_starts_with($resp, '235')) {
                error_log("Email: AUTH failed — $resp");
                fclose($conn);
                return false;
            }

            $cmd("MAIL FROM:<$from>");
            $cmd("RCPT TO:<$to>");
            $cmd('DATA');

            $boundary = bin2hex(random_bytes(8));
            $plain    = wordwrap(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), 76, "\n");
            $msg  = "From: \"Women Empowerment Initiatives\" <$from>\r\n";
            $msg .= "To: $to\r\n";
            $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $msg .= "\r\n";
            $msg .= "--$boundary\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $msg .= quoted_printable_encode($plain) . "\r\n";
            $msg .= "--$boundary\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $msg .= quoted_printable_encode($html) . "\r\n";
            $msg .= "--$boundary--\r\n";
            $msg .= '.';

            $resp = $cmd($msg);
            $cmd('QUIT');
            fclose($conn);

            if (!str_starts_with($resp, '250')) {
                error_log("Email: DATA rejected — $resp");
                return false;
            }
            return true;
        } catch (Throwable $e) {
            error_log('Email exception: ' . $e->getMessage());
            return false;
        }
    }

    // ── HTML wrapper ──────────────────────────────────────────────────────────

    private static function wrap(
        string  $heading,
        string  $body,
        string  $unsubUrl  = '',
        string  $headerBg  = '#1a6b3c'
    ): string {
        $css         = self::$css;
        $year        = date('Y');
        $unsubBlock  = $unsubUrl
            ? "<p class=\"unsub\">Don't want these emails? <a href=\"$unsubUrl\">Unsubscribe</a></p>"
            : '';

        // Inline WEI logo as SVG (64×64, works in all email clients)
        $logo = '<table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 12px"><tr>'
              . '<td style="width:64px;height:64px;background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.4);'
              . 'border-radius:50%;text-align:center;vertical-align:middle;font-family:Arial,sans-serif;'
              . 'font-size:19px;font-weight:900;color:#fff;letter-spacing:2px">WEI</td>'
              . '</tr></table>';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <meta name="color-scheme" content="light">
          <style>$css</style>
        </head>
        <body>
          <table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 16px;background:#f0f2f0">
            <tr><td align="center">
              <table class="wrap" width="620" cellpadding="0" cellspacing="0">

                <!-- Header -->
                <tr>
                  <td class="hdr" style="background:$headerBg">
                    <div class="hdr-inner">
                      $logo
                      <h1>Women Empowerment Initiatives</h1>
                      <p class="hdr-sub">Empowering Women Across Tanzania</p>
                    </div>
                    <!-- Tanzania flag stripe -->
                    <div class="flag-stripe"></div>
                  </td>
                </tr>

                <!-- Body -->
                <tr>
                  <td class="body">
                    <h2>$heading</h2>
                    $body
                  </td>
                </tr>

                <!-- Footer -->
                <tr>
                  <td class="ftr">
                    <p><strong>Women Empowerment Initiatives (WEI)</strong></p>
                    <p>Dodoma — Makulu, Tanzania</p>
                    <div class="ftr-links">
                      <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> &bull;
                      <a href="tel:+255743111867">+255 743 111 867</a> &bull;
                      <a href="https://wei.or.tz">wei.or.tz</a>
                    </div>
                    <p>&copy; $year Women Empowerment Initiatives. All rights reserved.</p>
                    $unsubBlock
                  </td>
                </tr>

              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }
}
