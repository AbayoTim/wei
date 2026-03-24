const { DataTypes } = require('sequelize');
const sequelize = require('../config/database');
const crypto = require('crypto');

const Subscriber = sequelize.define('Subscriber', {
  id: {
    type: DataTypes.UUID,
    defaultValue: DataTypes.UUIDV4,
    primaryKey: true
  },
  email: {
    type: DataTypes.STRING,
    allowNull: false,
    unique: true,
    validate: {
      isEmail: true
    }
  },
  name: {
    type: DataTypes.STRING
  },
  status: {
    type: DataTypes.ENUM('pending', 'confirmed', 'unsubscribed'),
    defaultValue: 'confirmed'
  },
  confirmationToken: {
    type: DataTypes.STRING
  },
  confirmedAt: {
    type: DataTypes.DATE
  },
  unsubscribedAt: {
    type: DataTypes.DATE
  },
  source: {
    type: DataTypes.STRING,
    defaultValue: 'website'
  }
});

Subscriber.beforeCreate((subscriber) => {
  subscriber.confirmationToken = crypto.randomBytes(32).toString('hex');
});

module.exports = Subscriber;
