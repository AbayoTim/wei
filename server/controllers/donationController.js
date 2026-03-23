const { Donation, User } = require('../models');
const { sendEmail, notifyAdmin } = require('../utils/email');
const { paginate, formatPaginationResponse, sanitizeHtml } = require('../utils/helpers');
const path = require('path');
const fs = require('fs');

// Submit donation
exports.submitDonation = async (req, res) => {
  try {
    const {
      donorName,
      donorEmail,
      donorPhone,
      amount,
      currency,
      paymentMethod,
      transactionReference,
      message,
      cause,
      isAnonymous
    } = req.body;

    // Get receipt file path
    let receiptFile = null;
    if (req.file) {
      receiptFile = req.file.filename;
    }

    const donation = await Donation.create({
      donorName: sanitizeHtml(donorName),
      donorEmail,
      donorPhone: sanitizeHtml(donorPhone),
      amount,
      currency: currency || 'TZS',
      paymentMethod: sanitizeHtml(paymentMethod),
      transactionReference: sanitizeHtml(transactionReference),
      receiptFile,
      message: sanitizeHtml(message),
      cause: sanitizeHtml(cause),
      isAnonymous: isAnonymous === 'true' || isAnonymous === true
    });

    // Send confirmation email to donor
    await sendEmail(donorEmail, 'donationReceived', [
      donorName,
      amount,
      currency || 'TZS',
      donation.referenceNumber
    ]);

    // Notify admin
    await notifyAdmin('Donation Submission', {
      'Reference Number': donation.referenceNumber,
      Donor: isAnonymous ? 'Anonymous' : donorName,
      Email: donorEmail,
      Amount: `${currency || 'TZS'} ${amount}`,
      'Payment Method': paymentMethod || 'Not specified',
      'Transaction Reference': transactionReference || 'Not provided',
      Status: 'Pending Verification'
    });

    res.status(201).json({
      success: true,
      message: 'Donation submitted successfully. We will verify your payment and send a confirmation.',
      data: {
        referenceNumber: donation.referenceNumber
      }
    });
  } catch (error) {
    console.error('Donation submit error:', error);
    res.status(500).json({
      success: false,
      message: 'Server error. Please try again later.'
    });
  }
};

// Get all donations (admin)
exports.getDonations = async (req, res) => {
  try {
    const { page = 1, limit = 20, status } = req.query;

    const where = {};
    if (status) {
      where.status = status;
    }

    const { count, rows } = await Donation.findAndCountAll({
      where,
      ...paginate({}, { page, limit }),
      include: [{
        model: User,
        as: 'approver',
        attributes: ['id', 'name']
      }],
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

// Get single donation (admin)
exports.getDonation = async (req, res) => {
  try {
    const { id } = req.params;

    const donation = await Donation.findByPk(id, {
      include: [{
        model: User,
        as: 'approver',
        attributes: ['id', 'name']
      }]
    });

    if (!donation) {
      return res.status(404).json({
        success: false,
        message: 'Donation not found'
      });
    }

    res.json({
      success: true,
      data: donation
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Approve donation (admin)
exports.approveDonation = async (req, res) => {
  try {
    const { id } = req.params;

    const donation = await Donation.findByPk(id);

    if (!donation) {
      return res.status(404).json({
        success: false,
        message: 'Donation not found'
      });
    }

    if (donation.status !== 'pending') {
      return res.status(400).json({
        success: false,
        message: 'Only pending donations can be approved'
      });
    }

    await donation.update({
      status: 'approved',
      approvedAt: new Date(),
      approvedBy: req.userId
    });

    // Send confirmation email to donor
    await sendEmail(donation.donorEmail, 'donationApproved', [
      donation.donorName,
      donation.amount,
      donation.currency,
      donation.referenceNumber
    ]);

    res.json({
      success: true,
      message: 'Donation approved successfully',
      data: donation
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Reject donation (admin)
exports.rejectDonation = async (req, res) => {
  try {
    const { id } = req.params;
    const { reason } = req.body;

    const donation = await Donation.findByPk(id);

    if (!donation) {
      return res.status(404).json({
        success: false,
        message: 'Donation not found'
      });
    }

    if (donation.status !== 'pending') {
      return res.status(400).json({
        success: false,
        message: 'Only pending donations can be rejected'
      });
    }

    await donation.update({
      status: 'rejected',
      rejectionReason: reason,
      approvedBy: req.userId
    });

    // Send notification email to donor
    await sendEmail(donation.donorEmail, 'donationRejected', [
      donation.donorName,
      donation.referenceNumber,
      reason
    ]);

    res.json({
      success: true,
      message: 'Donation rejected',
      data: donation
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get donation stats (admin)
exports.getStats = async (req, res) => {
  try {
    const { Donation } = require('../models');
    const { Op, fn, col } = require('sequelize');

    const total = await Donation.count();
    const pending = await Donation.count({ where: { status: 'pending' } });
    const approved = await Donation.count({ where: { status: 'approved' } });
    const rejected = await Donation.count({ where: { status: 'rejected' } });

    const totalAmount = await Donation.sum('amount', {
      where: { status: 'approved' }
    }) || 0;

    res.json({
      success: true,
      data: {
        total,
        pending,
        approved,
        rejected,
        totalAmount
      }
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get approved donations (public - for display)
exports.getApprovedDonations = async (req, res) => {
  try {
    const { limit = 10 } = req.query;

    const donations = await Donation.findAll({
      where: {
        status: 'approved',
        isAnonymous: false
      },
      attributes: ['donorName', 'amount', 'currency', 'cause', 'approvedAt'],
      order: [['approvedAt', 'DESC']],
      limit: parseInt(limit)
    });

    res.json({
      success: true,
      data: donations
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get receipt file (admin)
exports.getReceipt = async (req, res) => {
  try {
    const { filename } = req.params;

    // Validate filename: must be UUID + allowed extension, no path traversal
    const SAFE_FILENAME = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(jpg|jpeg|png|gif|pdf)$/i;
    if (!SAFE_FILENAME.test(filename)) {
      return res.status(400).json({ success: false, message: 'Invalid filename.' });
    }

    const filePath = path.join(__dirname, '../uploads/receipts', filename);

    if (!fs.existsSync(filePath)) {
      return res.status(404).json({
        success: false,
        message: 'Receipt not found'
      });
    }

    res.sendFile(filePath);
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};
