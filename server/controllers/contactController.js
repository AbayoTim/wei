const { Contact } = require('../models');
const { sendEmail, notifyAdmin } = require('../utils/email');
const { paginate, formatPaginationResponse, getClientIp, sanitizeHtml } = require('../utils/helpers');

// Submit contact form
exports.submitContact = async (req, res) => {
  try {
    const { firstName, lastName, email, phone, subject, message } = req.body;

    const contact = await Contact.create({
      firstName: sanitizeHtml(firstName),
      lastName: sanitizeHtml(lastName),
      email,
      phone: sanitizeHtml(phone),
      subject: sanitizeHtml(subject),
      message: sanitizeHtml(message),
      ipAddress: getClientIp(req)
    });

    // Send confirmation email to user
    await sendEmail(email, 'contactReceived', [firstName]);

    // Notify admin
    await notifyAdmin('Contact Message', {
      Name: `${firstName} ${lastName}`,
      Email: email,
      Phone: phone || 'Not provided',
      Subject: subject || 'Not specified',
      Message: message.substring(0, 200) + (message.length > 200 ? '...' : '')
    });

    res.status(201).json({
      success: true,
      message: 'Your message has been received. We will get back to you soon.'
    });
  } catch (error) {
    console.error('Contact submit error:', error);
    res.status(500).json({
      success: false,
      message: 'Server error. Please try again later.'
    });
  }
};

// Get all contacts (admin)
exports.getContacts = async (req, res) => {
  try {
    const { page = 1, limit = 20, status } = req.query;

    const where = {};
    if (status) {
      where.status = status;
    }

    const { count, rows } = await Contact.findAndCountAll({
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

// Get single contact (admin)
exports.getContact = async (req, res) => {
  try {
    const { id } = req.params;

    const contact = await Contact.findByPk(id);

    if (!contact) {
      return res.status(404).json({
        success: false,
        message: 'Contact not found'
      });
    }

    // Mark as read if new
    if (contact.status === 'new') {
      await contact.update({ status: 'read' });
    }

    res.json({
      success: true,
      data: contact
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Update contact status (admin)
exports.updateContact = async (req, res) => {
  try {
    const { id } = req.params;
    const { status, replyMessage } = req.body;

    const contact = await Contact.findByPk(id);

    if (!contact) {
      return res.status(404).json({
        success: false,
        message: 'Contact not found'
      });
    }

    const updateData = { status };

    if (status === 'replied') {
      updateData.repliedAt = new Date();
      updateData.replyMessage = replyMessage;
    }

    await contact.update(updateData);

    // Send reply email to the contact person
    if (status === 'replied' && replyMessage) {
      await sendEmail(contact.email, 'contactReplied', [contact.firstName, replyMessage]);
    }

    res.json({
      success: true,
      message: 'Contact updated successfully',
      data: contact
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Delete contact (admin)
exports.deleteContact = async (req, res) => {
  try {
    const { id } = req.params;

    const contact = await Contact.findByPk(id);

    if (!contact) {
      return res.status(404).json({
        success: false,
        message: 'Contact not found'
      });
    }

    await contact.destroy();

    res.json({
      success: true,
      message: 'Contact deleted successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get contact stats (admin)
exports.getStats = async (req, res) => {
  try {
    const total = await Contact.count();
    const newCount = await Contact.count({ where: { status: 'new' } });
    const read = await Contact.count({ where: { status: 'read' } });
    const replied = await Contact.count({ where: { status: 'replied' } });
    const archived = await Contact.count({ where: { status: 'archived' } });

    res.json({
      success: true,
      data: {
        total,
        new: newCount,
        read,
        replied,
        archived
      }
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};
