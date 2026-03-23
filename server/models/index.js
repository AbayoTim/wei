const sequelize = require('../config/database');
const User = require('./User');
const Blog = require('./Blog');
const Subscriber = require('./Subscriber');
const Contact = require('./Contact');
const Donation = require('./Donation');
const Partner = require('./Partner');
const SiteContent = require('./SiteContent');
const Event = require('./Event');
const Cause = require('./Cause');
const TeamMember = require('./TeamMember');
const Media = require('./Media');

// Associations
Blog.belongsTo(User, { as: 'author', foreignKey: 'authorId' });
User.hasMany(Blog, { foreignKey: 'authorId' });

Donation.belongsTo(User, { as: 'approver', foreignKey: 'approvedBy' });

// Sync function
const syncDatabase = async (force = false) => {
  try {
    await sequelize.sync({ force });
    console.log('Database synchronized successfully');
  } catch (error) {
    console.error('Error synchronizing database:', error);
    throw error;
  }
};

module.exports = {
  sequelize,
  syncDatabase,
  User,
  Blog,
  Subscriber,
  Contact,
  Donation,
  Partner,
  SiteContent,
  Event,
  Cause,
  TeamMember,
  Media
};
