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

// Email templates
const templates = {
  subscriptionConfirmation: (name, confirmUrl) => ({
    subject: 'Confirm Your Newsletter Subscription - WEI',
    html: `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { background: #6c63ff; color: white; padding: 20px; text-align: center; }
          .content { padding: 20px; background: #f9f9f9; }
          .button { display: inline-block; padding: 12px 24px; background: #6c63ff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <h1>Women Empowerment Initiatives</h1>
          </div>
          <div class="content">
            <h2>Hello${name ? ' ' + name : ''},</h2>
            <p>Thank you for subscribing to our newsletter! Please confirm your subscription by clicking the button below:</p>
            <p style="text-align: center;">
              <a href="${confirmUrl}" class="button">Confirm Subscription</a>
            </p>
            <p>If you did not subscribe to our newsletter, please ignore this email.</p>
            <p>Best regards,<br>Women Empowerment Initiatives Team</p>
          </div>
          <div class="footer">
            <p>Dodoma - Makulu, Tanzania | info@wei.or.tz | +255 743 111 867</p>
          </div>
        </div>
      </body>
      </html>
    `
  }),

  subscriptionConfirmed: (name) => ({
    subject: 'Subscription Confirmed - WEI',
    html: `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { background: #6c63ff; color: white; padding: 20px; text-align: center; }
          .content { padding: 20px; background: #f9f9f9; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <h1>Women Empowerment Initiatives</h1>
          </div>
          <div class="content">
            <h2>Welcome${name ? ' ' + name : ''}!</h2>
            <p>Your subscription has been confirmed successfully!</p>
            <p>You will now receive our latest updates, news, and opportunities to support women empowerment in Tanzania.</p>
            <p>Best regards,<br>Women Empowerment Initiatives Team</p>
          </div>
          <div class="footer">
            <p>Dodoma - Makulu, Tanzania | info@wei.or.tz | +255 743 111 867</p>
          </div>
        </div>
      </body>
      </html>
    `
  }),

  contactReceived: (firstName) => ({
    subject: 'We Received Your Message - WEI',
    html: `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { background: #6c63ff; color: white; padding: 20px; text-align: center; }
          .content { padding: 20px; background: #f9f9f9; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <h1>Women Empowerment Initiatives</h1>
          </div>
          <div class="content">
            <h2>Hello ${firstName},</h2>
            <p>Thank you for contacting us! We have received your message and will get back to you as soon as possible.</p>
            <p>If your matter is urgent, please feel free to call us at +255 743 111 867.</p>
            <p>Best regards,<br>Women Empowerment Initiatives Team</p>
          </div>
          <div class="footer">
            <p>Dodoma - Makulu, Tanzania | info@wei.or.tz | +255 743 111 867</p>
          </div>
        </div>
      </body>
      </html>
    `
  }),

  donationReceived: (donorName, amount, currency, referenceNumber) => ({
    subject: 'Donation Received - Pending Verification - WEI',
    html: `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { background: #6c63ff; color: white; padding: 20px; text-align: center; }
          .content { padding: 20px; background: #f9f9f9; }
          .info-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <h1>Women Empowerment Initiatives</h1>
          </div>
          <div class="content">
            <h2>Thank You, ${donorName}!</h2>
            <p>We have received your donation submission. Our team will verify your payment receipt and confirm your donation shortly.</p>
            <div class="info-box">
              <p><strong>Reference Number:</strong> ${referenceNumber}</p>
              <p><strong>Amount:</strong> ${currency} ${amount}</p>
              <p><strong>Status:</strong> Pending Verification</p>
            </div>
            <p>You will receive a confirmation email once your donation has been verified.</p>
            <p>Best regards,<br>Women Empowerment Initiatives Team</p>
          </div>
          <div class="footer">
            <p>Dodoma - Makulu, Tanzania | info@wei.or.tz | +255 743 111 867</p>
          </div>
        </div>
      </body>
      </html>
    `
  }),

  donationApproved: (donorName, amount, currency, referenceNumber) => ({
    subject: 'Donation Confirmed - Thank You! - WEI',
    html: `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { background: #28a745; color: white; padding: 20px; text-align: center; }
          .content { padding: 20px; background: #f9f9f9; }
          .info-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <h1>Donation Confirmed!</h1>
          </div>
          <div class="content">
            <h2>Thank You, ${donorName}!</h2>
            <p>Your generous donation has been verified and confirmed. Your support makes a real difference in empowering women and communities in Tanzania.</p>
            <div class="info-box">
              <p><strong>Reference Number:</strong> ${referenceNumber}</p>
              <p><strong>Amount:</strong> ${currency} ${amount}</p>
              <p><strong>Status:</strong> Confirmed</p>
            </div>
            <p>Thank you for being part of our mission to empower women!</p>
            <p>Best regards,<br>Women Empowerment Initiatives Team</p>
          </div>
          <div class="footer">
            <p>Dodoma - Makulu, Tanzania | info@wei.or.tz | +255 743 111 867</p>
          </div>
        </div>
      </body>
      </html>
    `
  }),

  donationRejected: (donorName, referenceNumber, reason) => ({
    subject: 'Donation Verification Issue - WEI',
    html: `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
          .content { padding: 20px; background: #f9f9f9; }
          .info-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <h1>Verification Issue</h1>
          </div>
          <div class="content">
            <h2>Hello ${donorName},</h2>
            <p>We were unable to verify your donation submission.</p>
            <div class="info-box">
              <p><strong>Reference Number:</strong> ${referenceNumber}</p>
              <p><strong>Reason:</strong> ${reason || 'Unable to verify payment receipt'}</p>
            </div>
            <p>If you believe this is an error, please contact us at info@wei.or.tz or call +255 743 111 867 with your reference number.</p>
            <p>Best regards,<br>Women Empowerment Initiatives Team</p>
          </div>
          <div class="footer">
            <p>Dodoma - Makulu, Tanzania | info@wei.or.tz | +255 743 111 867</p>
          </div>
        </div>
      </body>
      </html>
    `
  }),

  contactReplied: (firstName, replyMessage) => ({
    subject: 'Response to Your Message - WEI',
    html: `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
          .container { max-width: 600px; margin: 0 auto; padding: 20px; }
          .header { background: #1a6b3c; color: white; padding: 20px; text-align: center; }
          .content { padding: 20px; background: #f9f9f9; }
          .reply-box { background: white; padding: 15px; border-left: 4px solid #1a6b3c; margin: 15px 0; border-radius: 0 5px 5px 0; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <h1>Women Empowerment Initiatives</h1>
          </div>
          <div class="content">
            <h2>Hello ${firstName},</h2>
            <p>We have reviewed your message and have a response for you:</p>
            <div class="reply-box">
              <p>${replyMessage}</p>
            </div>
            <p>If you have any further questions, please don't hesitate to contact us at <a href="mailto:info@wei.or.tz">info@wei.or.tz</a> or call +255 743 111 867.</p>
            <p>Best regards,<br>Women Empowerment Initiatives Team</p>
          </div>
          <div class="footer">
            <p>Dodoma - Makulu, Tanzania | info@wei.or.tz | +255 743 111 867</p>
          </div>
        </div>
      </body>
      </html>
    `
  }),

  adminNotification: (type, details) => {
    // Escape values to prevent XSS in admin emails
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    return {
      subject: `[WEI Admin] New ${type}`,
      html: `
        <!DOCTYPE html>
        <html>
        <head>
          <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #343a40; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .info-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
          </style>
        </head>
        <body>
          <div class="container">
            <div class="header">
              <h1>Admin Notification</h1>
            </div>
            <div class="content">
              <h2>New ${esc(type)}</h2>
              <div class="info-box">
                ${Object.entries(details).map(([key, value]) => `<p><strong>${esc(key)}:</strong> ${esc(value)}</p>`).join('')}
              </div>
              <p><a href="${esc(process.env.FRONTEND_URL)}/admin">Go to Admin Panel</a></p>
            </div>
          </div>
        </body>
        </html>
      `
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
