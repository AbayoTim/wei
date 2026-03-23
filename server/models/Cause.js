const { DataTypes } = require('sequelize');
const sequelize = require('../config/database');

const Cause = sequelize.define('Cause', {
  id: {
    type: DataTypes.UUID,
    defaultValue: DataTypes.UUIDV4,
    primaryKey: true
  },
  title: {
    type: DataTypes.STRING,
    allowNull: false
  },
  slug: {
    type: DataTypes.STRING,
    allowNull: false,
    unique: true
  },
  description: {
    type: DataTypes.TEXT
  },
  content: {
    type: DataTypes.TEXT
  },
  featuredImage: {
    type: DataTypes.STRING
  },
  category: {
    type: DataTypes.ENUM('education', 'health', 'livelihood', 'advocacy', 'other'),
    defaultValue: 'other'
  },
  goalAmount: {
    type: DataTypes.DECIMAL(10, 2),
    defaultValue: 0
  },
  raisedAmount: {
    type: DataTypes.DECIMAL(10, 2),
    defaultValue: 0
  },
  currency: {
    type: DataTypes.STRING,
    defaultValue: 'TZS'
  },
  startDate: {
    type: DataTypes.DATE
  },
  endDate: {
    type: DataTypes.DATE
  },
  status: {
    type: DataTypes.ENUM('active', 'completed', 'paused'),
    defaultValue: 'active'
  },
  isFeatured: {
    type: DataTypes.BOOLEAN,
    defaultValue: false
  },
  isPublished: {
    type: DataTypes.BOOLEAN,
    defaultValue: false
  }
});

Cause.beforeCreate((cause) => {
  if (!cause.slug) {
    cause.slug = cause.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');
  }
});

module.exports = Cause;
