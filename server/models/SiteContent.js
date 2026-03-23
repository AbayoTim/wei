const { DataTypes } = require('sequelize');
const sequelize = require('../config/database');

const SiteContent = sequelize.define('SiteContent', {
  id: {
    type: DataTypes.UUID,
    defaultValue: DataTypes.UUIDV4,
    primaryKey: true
  },
  key: {
    type: DataTypes.STRING,
    allowNull: false,
    unique: true
  },
  value: {
    type: DataTypes.TEXT,
    get() {
      const rawValue = this.getDataValue('value');
      try {
        return JSON.parse(rawValue);
      } catch {
        return rawValue;
      }
    },
    set(value) {
      if (typeof value === 'object') {
        this.setDataValue('value', JSON.stringify(value));
      } else {
        this.setDataValue('value', value);
      }
    }
  },
  type: {
    type: DataTypes.ENUM('text', 'html', 'json', 'image'),
    defaultValue: 'text'
  },
  description: {
    type: DataTypes.STRING
  },
  lastUpdatedBy: {
    type: DataTypes.UUID
  }
});

module.exports = SiteContent;
