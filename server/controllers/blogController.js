const { Blog, User } = require('../models');
const { paginate, formatPaginationResponse, generateSlug } = require('../utils/helpers');
const { Op } = require('sequelize');

// Get all blogs (public - published only, admin - all)
exports.getBlogs = async (req, res) => {
  try {
    const { page = 1, limit = 10, category, search, status } = req.query;
    const isAdmin = req.user && req.user.role === 'admin';

    const where = {};

    // Only show published blogs to public
    if (!isAdmin) {
      where.status = 'published';
    } else if (status) {
      where.status = status;
    }

    if (category) {
      where.category = category;
    }

    if (search) {
      where[Op.or] = [
        { title: { [Op.like]: `%${search}%` } },
        { content: { [Op.like]: `%${search}%` } }
      ];
    }

    const { count, rows } = await Blog.findAndCountAll({
      where,
      ...paginate({}, { page, limit }),
      include: [{
        model: User,
        as: 'author',
        attributes: ['id', 'name']
      }],
      order: [['publishedAt', 'DESC'], ['createdAt', 'DESC']]
    });

    res.json({
      success: true,
      ...formatPaginationResponse(rows, count, { page, limit })
    });
  } catch (error) {
    console.error('Get blogs error:', error);
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get single blog
exports.getBlog = async (req, res) => {
  try {
    const { slug } = req.params;
    const isAdmin = req.user && req.user.role === 'admin';

    const where = { slug };
    if (!isAdmin) {
      where.status = 'published';
    }

    const blog = await Blog.findOne({
      where,
      include: [{
        model: User,
        as: 'author',
        attributes: ['id', 'name']
      }]
    });

    if (!blog) {
      return res.status(404).json({
        success: false,
        message: 'Blog not found'
      });
    }

    // Increment views for public access
    if (!isAdmin) {
      await blog.increment('views');
    }

    res.json({
      success: true,
      data: blog
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Create blog
exports.createBlog = async (req, res) => {
  try {
    const { title, content, excerpt, category, tags, status, featuredImage, gallery } = req.body;

    let slug = generateSlug(title);

    // Check for existing slug
    const existingBlog = await Blog.findOne({ where: { slug } });
    if (existingBlog) {
      slug = `${slug}-${Date.now()}`;
    }

    const parsedGallery = typeof gallery === 'string' ? JSON.parse(gallery || '[]') : (gallery || []);

    const blog = await Blog.create({
      title,
      slug,
      content,
      excerpt,
      category,
      tags,
      status,
      featuredImage,
      gallery: parsedGallery,
      authorId: req.userId
    });

    res.status(201).json({
      success: true,
      message: 'Blog created successfully',
      data: blog
    });
  } catch (error) {
    console.error('Create blog error:', error);
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Update blog
exports.updateBlog = async (req, res) => {
  try {
    const { id } = req.params;
    const { title, content, excerpt, category, tags, status, featuredImage, gallery } = req.body;

    const blog = await Blog.findByPk(id);

    if (!blog) {
      return res.status(404).json({
        success: false,
        message: 'Blog not found'
      });
    }

    // Update slug if title changed
    let slug = blog.slug;
    if (title && title !== blog.title) {
      slug = generateSlug(title);
      const existingBlog = await Blog.findOne({
        where: { slug, id: { [Op.ne]: id } }
      });
      if (existingBlog) {
        slug = `${slug}-${Date.now()}`;
      }
    }

    const parsedGallery = typeof gallery === 'string' ? JSON.parse(gallery || '[]') : (gallery || blog.gallery);

    await blog.update({
      title,
      slug,
      content,
      excerpt,
      category,
      tags,
      status,
      featuredImage,
      gallery: parsedGallery
    });

    res.json({
      success: true,
      message: 'Blog updated successfully',
      data: blog
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Delete blog
exports.deleteBlog = async (req, res) => {
  try {
    const { id } = req.params;

    const blog = await Blog.findByPk(id);

    if (!blog) {
      return res.status(404).json({
        success: false,
        message: 'Blog not found'
      });
    }

    await blog.destroy();

    res.json({
      success: true,
      message: 'Blog deleted successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get blog categories
exports.getCategories = async (req, res) => {
  try {
    const blogs = await Blog.findAll({
      attributes: ['category'],
      group: ['category'],
      where: { status: 'published' }
    });

    const categories = blogs.map(b => b.category).filter(Boolean);

    res.json({
      success: true,
      data: categories
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};
