// Honeypot middleware — silently rejects bots that fill hidden fields
const honeypot = (req, res, next) => {
  const val = req.body?.website;
  if (val !== undefined && val !== null && String(val).trim() !== '') {
    // Return a plausible success response so bots don't retry
    const path = req.originalUrl;
    let msg = 'Your submission was received.';
    if (path.includes('subscribe')) msg = 'Please check your email to confirm your subscription.';
    else if (path.includes('contact')) msg = 'Your message has been received. We will get back to you soon.';
    else if (path.includes('donation')) msg = 'Donation submitted successfully. We will verify your payment and send a confirmation.';
    return res.status(200).json({ success: true, message: msg });
  }
  next();
};

module.exports = honeypot;
