const nodemailer = require('nodemailer');

// Create transporter
const createTransporter = () => {
  return nodemailer.createTransport({
    host: process.env.SMTP_HOST,
    port: process.env.SMTP_PORT,
    secure: process.env.SMTP_PORT === '465',
    auth: {
      user: process.env.SMTP_USER,
      pass: process.env.SMTP_PASS
    }
  });
};

// Shared CSS for all templates
const baseStyles = `
  body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
  .container { max-width: 600px; margin: 0 auto; padding: 20px; }
  .header { background: #1a6b3c; color: white; padding: 24px 20px; text-align: center; border-radius: 6px 6px 0 0; }
  .header h1 { margin: 0; font-size: 22px; letter-spacing: 0.5px; }
  .header .tagline { margin: 4px 0 0; font-size: 13px; opacity: 0.85; }
  .content { padding: 28px 24px; background: #f9f9f9; }
  .content h2 { margin-top: 0; color: #1a6b3c; }
  .info-box { background: white; padding: 16px; border-radius: 6px; margin: 16px 0; border: 1px solid #e0e0e0; }
  .info-box p { margin: 6px 0; }
  .button { display: inline-block; padding: 12px 28px; background: #1a6b3c; color: white !important; text-decoration: none; border-radius: 5px; margin: 16px 0; font-weight: bold; }
  .reply-box { background: white; padding: 16px; border-left: 4px solid #1a6b3c; margin: 16px 0; border-radius: 0 6px 6px 0; }
  .footer { padding: 18px 20px; text-align: center; font-size: 12px; color: #888; background: #f0f0f0; border-radius: 0 0 6px 6px; border-top: 1px solid #e0e0e0; }
  .unsubscribe { margin-top: 10px; font-size: 11px; color: #aaa; }
  .unsubscribe a { color: #aaa; }
`;

// Email templates
const templates = {
  subscriptionWelcome: (name, unsubscribeUrl) => ({
    subject: 'Welcome to WEI Newsletter!',
    html: `<!DOCTYPE html><html><head><style>${baseStyles}</style></head><body>
      <div class="container">
        <div class="header">
          <h1>Women Empowerment Initiatives</h1>
          <p class="tagline">Empowering Women Across Tanzania</p>
        </div>
        <div class="content">
          <h2>Welcome${name ? ', ' + name : ''}!</h2>
          <p>Thank you for subscribing to our newsletter. You are now part of our community!</p>
          <p>You will receive our latest news, updates, and opportunities to support women empowerment in Tanzania.</p>
          <p>Best regards,<br><strong>Women Empowerment Initiatives Team</strong></p>
        </div>
        <div class="footer">
          <p>Dodoma - Makulu, Tanzania &bull; <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> &bull; +255 743 111 867</p>
          ${unsubscribeUrl ? `<p class="unsubscribe">Don't want to receive these emails? <a href="${unsubscribeUrl}">Unsubscribe</a></p>` : ''}
        </div>
      </div>
    </body></html>`
  }),

  // Legacy alias — kept so any old code path still works
  subscriptionConfirmed: (name, unsubscribeUrl) => templates.subscriptionWelcome(name, unsubscribeUrl),

  contactReceived: (firstName) => ({
    subject: 'We Received Your Message - WEI',
    html: `<!DOCTYPE html><html><head><style>${baseStyles}</style></head><body>
      <div class="container">
        <div class="header">
          <h1>Women Empowerment Initiatives</h1>
          <p class="tagline">Empowering Women Across Tanzania</p>
        </div>
        <div class="content">
          <h2>Hello ${firstName},</h2>
          <p>Thank you for contacting us! We have received your message and will get back to you as soon as possible.</p>
          <p>If your matter is urgent, please feel free to call us at <strong>+255 743 111 867</strong>.</p>
          <p>Best regards,<br><strong>Women Empowerment Initiatives Team</strong></p>
        </div>
        <div class="footer">
          <p>Dodoma - Makulu, Tanzania &bull; <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> &bull; +255 743 111 867</p>
        </div>
      </div>
    </body></html>`
  }),

  donationReceived: (donorName, amount, currency, referenceNumber) => ({
    subject: 'Donation Received — Pending Verification | WEI',
    html: `<!DOCTYPE html><html><head><style>${baseStyles}</style></head><body>
      <div class="container">
        <div class="header">
          <h1>Women Empowerment Initiatives</h1>
          <p class="tagline">Empowering Women Across Tanzania</p>
        </div>
        <div class="content">
          <h2>Thank You, ${donorName}!</h2>
          <p>We have received your donation submission. Our team will verify your payment receipt and confirm your donation shortly.</p>
          <div class="info-box">
            <p><strong>Reference Number:</strong> ${referenceNumber}</p>
            <p><strong>Amount:</strong> ${currency} ${Number(amount).toLocaleString()}</p>
            <p><strong>Status:</strong> Pending Verification</p>
          </div>
          <p>You will receive a confirmation email once your donation has been verified.</p>
          <p>Best regards,<br><strong>Women Empowerment Initiatives Team</strong></p>
        </div>
        <div class="footer">
          <p>Dodoma - Makulu, Tanzania &bull; <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> &bull; +255 743 111 867</p>
        </div>
      </div>
    </body></html>`
  }),

  donationApproved: (donorName, amount, currency, referenceNumber) => ({
    subject: 'Donation Confirmed — Thank You! | WEI',
    html: `<!DOCTYPE html><html><head><style>${baseStyles}</style></head><body>
      <div class="container">
        <div class="header" style="background:#1a6b3c;">
          <h1>Donation Confirmed! ✓</h1>
          <p class="tagline">Women Empowerment Initiatives</p>
        </div>
        <div class="content">
          <h2>Thank You, ${donorName}!</h2>
          <p>Your generous donation has been verified and confirmed. Your support makes a real difference in empowering women and communities in Tanzania.</p>
          <div class="info-box">
            <p><strong>Reference Number:</strong> ${referenceNumber}</p>
            <p><strong>Amount:</strong> ${currency} ${Number(amount).toLocaleString()}</p>
            <p><strong>Status:</strong> ✓ Confirmed</p>
          </div>
          <p>Thank you for being part of our mission to empower women!</p>
          <p>Best regards,<br><strong>Women Empowerment Initiatives Team</strong></p>
        </div>
        <div class="footer">
          <p>Dodoma - Makulu, Tanzania &bull; <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> &bull; +255 743 111 867</p>
        </div>
      </div>
    </body></html>`
  }),

  donationRejected: (donorName, referenceNumber, reason) => ({
    subject: 'Donation Verification Issue | WEI',
    html: `<!DOCTYPE html><html><head><style>${baseStyles}</style></head><body>
      <div class="container">
        <div class="header" style="background:#c0392b;">
          <h1>Verification Issue</h1>
          <p class="tagline">Women Empowerment Initiatives</p>
        </div>
        <div class="content">
          <h2>Hello ${donorName},</h2>
          <p>We were unable to verify your donation submission. Please review the details below.</p>
          <div class="info-box">
            <p><strong>Reference Number:</strong> ${referenceNumber}</p>
            <p><strong>Reason:</strong> ${reason || 'Unable to verify payment receipt'}</p>
          </div>
          <p>If you believe this is an error, please contact us at <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> or call <strong>+255 743 111 867</strong> quoting your reference number.</p>
          <p>Best regards,<br><strong>Women Empowerment Initiatives Team</strong></p>
        </div>
        <div class="footer">
          <p>Dodoma - Makulu, Tanzania &bull; <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> &bull; +255 743 111 867</p>
        </div>
      </div>
    </body></html>`
  }),

  contactReplied: (firstName, replyMessage) => ({
    subject: 'Response to Your Message | WEI',
    html: `<!DOCTYPE html><html><head><style>${baseStyles}</style></head><body>
      <div class="container">
        <div class="header">
          <h1>Women Empowerment Initiatives</h1>
          <p class="tagline">Empowering Women Across Tanzania</p>
        </div>
        <div class="content">
          <h2>Hello ${firstName},</h2>
          <p>We have reviewed your message and have a response for you:</p>
          <div class="reply-box">${replyMessage}</div>
          <p>If you have any further questions, please contact us at <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> or call <strong>+255 743 111 867</strong>.</p>
          <p>Best regards,<br><strong>Women Empowerment Initiatives Team</strong></p>
        </div>
        <div class="footer">
          <p>Dodoma - Makulu, Tanzania &bull; <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> &bull; +255 743 111 867</p>
        </div>
      </div>
    </body></html>`
  }),

  adminNotification: (type, details) => {
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    return {
      subject: `[WEI Admin] New ${type}`,
      html: `<!DOCTYPE html><html><head><style>${baseStyles}</style></head><body>
        <div class="container">
          <div class="header" style="background:#343a40;">
            <h1>Admin Notification</h1>
            <p class="tagline">WEI Internal System</p>
          </div>
          <div class="content">
            <h2>New ${esc(type)}</h2>
            <div class="info-box">
              ${Object.entries(details).map(([key, value]) => `<p><strong>${esc(key)}:</strong> ${esc(value)}</p>`).join('')}
            </div>
            <p><a href="${esc(process.env.FRONTEND_URL)}/admin" class="button">Go to Admin Panel</a></p>
          </div>
        </div>
      </body></html>`
    };
  }
};

// Send email function
const sendEmail = async (to, template, data) => {
  try {
    const transporter = createTransporter();
    const emailTemplate = templates[template](...data);

    const mailOptions = {
      from: `"Women Empowerment Initiatives" <${process.env.EMAIL_FROM}>`,
      to,
      subject: emailTemplate.subject,
      html: emailTemplate.html
    };

    await transporter.sendMail(mailOptions);
    return { success: true };
  } catch (error) {
    console.error('Email sending failed:', error);
    return { success: false, error: error.message };
  }
};

// Send admin notification
const notifyAdmin = async (type, details) => {
  try {
    const adminEmail = process.env.ADMIN_EMAIL;
    const transporter = createTransporter();
    const emailTemplate = templates.adminNotification(type, details);

    await transporter.sendMail({
      from: `"WEI System" <${process.env.EMAIL_FROM}>`,
      to: adminEmail,
      subject: emailTemplate.subject,
      html: emailTemplate.html
    });

    return { success: true };
  } catch (error) {
    console.error('Admin notification failed:', error);
    return { success: false, error: error.message };
  }
};

module.exports = { sendEmail, notifyAdmin };
