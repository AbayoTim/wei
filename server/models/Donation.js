const { DataTypes } = require('sequelize');
const sequelize = require('../config/database');
const crypto = require('crypto');

const Donation = sequelize.define('Donation', {
  id: {
    type: DataTypes.UUID,
    defaultValue: DataTypes.UUIDV4,
    primaryKey: true
  },
  referenceNumber: {
    type: DataTypes.STRING,
    unique: true
  },
  donorName: {
    type: DataTypes.STRING,
    allowNull: false
  },
  donorEmail: {
    type: DataTypes.STRING,
    allowNull: false,
    validate: {
      isEmail: true
    }
  },
  donorPhone: {
    type: DataTypes.STRING
  },
  amount: {
    type: DataTypes.DECIMAL(10, 2),
    allowNull: false
  },
  currency: {
    type: DataTypes.STRING,
    defaultValue: 'TZS'
  },
  paymentMethod: {
    type: DataTypes.STRING
  },
  transactionReference: {
    type: DataTypes.STRING
  },
  receiptFile: {
    type: DataTypes.STRING
  },
  message: {
    type: DataTypes.TEXT
  },
  cause: {
    type: DataTypes.STRING
  },
  status: {
    type: DataTypes.ENUM('pending', 'approved', 'rejected'),
    defaultValue: 'pending'
  },
  approvedAt: {
    type: DataTypes.DATE
  },
  approvedBy: {
    type: DataTypes.UUID
  },
  rejectionReason: {
    type: DataTypes.TEXT
  },
  isAnonymous: {
    type: DataTypes.BOOLEAN,
    defaultValue: false
  }
});

Donation.beforeCreate((donation) => {
  const timestamp = Date.now().toString(36).toUpperCase();
  const random = crypto.randomBytes(3).toString('hex').toUpperCase();
  donation.referenceNumber = `WEI-${timestamp}-${random}`;
});

module.exports = Donation;
