const { Subscriber } = require('../models');
const { sendEmail, notifyAdmin } = require('../utils/email');
const { paginate, formatPaginationResponse } = require('../utils/helpers');

// Subscribe
exports.subscribe = async (req, res) => {
  try {
    const { email, name } = req.body;

    // Check existing subscriber
    let subscriber = await Subscriber.findOne({ where: { email } });

    if (subscriber) {
      if (subscriber.status === 'confirmed') {
        return res.status(400).json({
          success: false,
          message: 'Email already subscribed'
        });
      }

      // Reactivate pending or unsubscribed
      await subscriber.update({
        status: 'confirmed',
        confirmedAt: new Date(),
        unsubscribedAt: null,
        name: name || subscriber.name
      });

      const unsubUrl = `${process.env.FRONTEND_URL}/api/subscribers/unsubscribe/${subscriber.confirmationToken}`;
      await sendEmail(subscriber.email, 'subscriptionConfirmed', [subscriber.name, unsubUrl]);

      return res.json({
        success: true,
        message: 'You have been subscribed successfully!'
      });
    }

    // Create new subscriber — immediately active
    subscriber = await Subscriber.create({
      email,
      name,
      status: 'confirmed',
      confirmedAt: new Date()
    });

    const unsubUrl = `${process.env.FRONTEND_URL}/api/subscribers/unsubscribe/${subscriber.confirmationToken}`;
    await sendEmail(email, 'subscriptionConfirmed', [name, unsubUrl]);

    await notifyAdmin('Newsletter Subscription', {
      Email: email,
      Name: name || 'Not provided',
      Status: 'Subscribed'
    });

    res.status(201).json({
      success: true,
      message: 'You have been subscribed successfully!'
    });
  } catch (error) {
    console.error('Subscribe error:', error);
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Confirm subscription
exports.confirmSubscription = async (req, res) => {
  try {
    const { token } = req.params;

    const subscriber = await Subscriber.findOne({
      where: { confirmationToken: token }
    });

    if (!subscriber) {
      return res.redirect(`${process.env.FRONTEND_URL}/subscription-error.html`);
    }

    if (subscriber.status === 'confirmed') {
      return res.redirect(`${process.env.FRONTEND_URL}/subscription-confirmed.html`);
    }

    await subscriber.update({
      status: 'confirmed',
      confirmedAt: new Date()
    });

    // Send welcome email
    const unsubUrl = `${process.env.FRONTEND_URL}/api/subscribers/unsubscribe/${subscriber.confirmationToken}`;
    await sendEmail(subscriber.email, 'subscriptionConfirmed', [subscriber.name, unsubUrl]);

    res.redirect(`${process.env.FRONTEND_URL}/subscription-confirmed.html`);
  } catch (error) {
    console.error('Confirm error:', error);
    res.redirect(`${process.env.FRONTEND_URL}/subscription-error.html`);
  }
};

// Unsubscribe
exports.unsubscribe = async (req, res) => {
  try {
    const { token } = req.params;

    const subscriber = await Subscriber.findOne({
      where: { confirmationToken: token }
    });

    if (!subscriber) {
      return res.redirect(`${process.env.FRONTEND_URL}/subscription-error.html`);
    }

    await subscriber.update({
      status: 'unsubscribed',
      unsubscribedAt: new Date()
    });

    res.redirect(`${process.env.FRONTEND_URL}/unsubscribed.html`);
  } catch (error) {
    res.redirect(`${process.env.FRONTEND_URL}/unsubscribe-error.html`);
  }
};

// Get all subscribers (admin)
exports.getSubscribers = async (req, res) => {
  try {
    const { page = 1, limit = 20, status } = req.query;

    const where = { status: 'confirmed' };
    if (status) {
      where.status = status;
    }

    const { count, rows } = await Subscriber.findAndCountAll({
      where,
      ...paginate({}, { page, limit }),
      order: [['createdAt', 'DESC']]
    });

    res.json({
      success: true,
      ...formatPaginationResponse(rows, count, { page, limit })
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get subscriber stats (admin)
exports.getStats = async (req, res) => {
  try {
    const confirmed = await Subscriber.count({ where: { status: 'confirmed' } });
    const unsubscribed = await Subscriber.count({ where: { status: 'unsubscribed' } });

    res.json({
      success: true,
      data: {
        confirmed,
        unsubscribed,
        total: confirmed + unsubscribed
      }
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Delete subscriber (admin)
exports.deleteSubscriber = async (req, res) => {
  try {
    const { id } = req.params;

    const subscriber = await Subscriber.findByPk(id);

    if (!subscriber) {
      return res.status(404).json({
        success: false,
        message: 'Subscriber not found'
      });
    }

    await subscriber.destroy();

    res.json({
      success: true,
      message: 'Subscriber deleted successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Export subscribers (admin)
exports.exportSubscribers = async (req, res) => {
  try {
    const subscribers = await Subscriber.findAll({
      where: { status: 'confirmed' },
      attributes: ['email', 'name', 'confirmedAt'],
      order: [['confirmedAt', 'DESC']]
    });

    // Wrap all values in double-quotes and escape internal quotes to prevent
    // CSV formula injection (e.g. =cmd|...) in spreadsheet applications
    const csvEscape = (v) => {
      const s = String(v ?? '');
      return `"${s.replace(/"/g, '""')}"`;
    };

    const csv = [
      '"Email","Name","Confirmed At"',
      ...subscribers.map(s => [
        csvEscape(s.email),
        csvEscape(s.name),
        csvEscape(s.confirmedAt)
      ].join(','))
    ].join('\n');

    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename=subscribers.csv');
    res.send(csv);
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};
