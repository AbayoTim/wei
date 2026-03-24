const { DataTypes } = require('sequelize');
const sequelize = require('../config/database');

const Blog = sequelize.define('Blog', {
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
  content: {
    type: DataTypes.TEXT,
    allowNull: false
  },
  excerpt: {
    type: DataTypes.TEXT
  },
  featuredImage: {
    type: DataTypes.STRING
  },
  category: {
    type: DataTypes.STRING,
    defaultValue: 'General'
  },
  tags: {
    type: DataTypes.TEXT,
    get() {
      const value = this.getDataValue('tags');
      return value ? JSON.parse(value) : [];
    },
    set(value) {
      this.setDataValue('tags', JSON.stringify(value || []));
    }
  },
  status: {
    type: DataTypes.ENUM('draft', 'published'),
    defaultValue: 'draft'
  },
  publishedAt: {
    type: DataTypes.DATE
  },
  views: {
    type: DataTypes.INTEGER,
    defaultValue: 0
  },
  gallery: {
    type: DataTypes.TEXT,
    get() {
      const v = this.getDataValue('gallery');
      return v ? JSON.parse(v) : [];
    },
    set(value) {
      this.setDataValue('gallery', JSON.stringify(value || []));
    }
  },
  authorId: {
    type: DataTypes.UUID,
    allowNull: false
  }
});

Blog.beforeCreate((blog) => {
  if (!blog.slug) {
    blog.slug = blog.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');
  }
  if (blog.status === 'published' && !blog.publishedAt) {
    blog.publishedAt = new Date();
  }
});

Blog.beforeUpdate((blog) => {
  if (blog.changed('status') && blog.status === 'published' && !blog.publishedAt) {
    blog.publishedAt = new Date();
  }
});

module.exports = Blog;
