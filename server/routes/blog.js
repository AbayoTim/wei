const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const blogController = require('../controllers/blogController');
const { auth, optionalAuth } = require('../middleware/auth');
const validate = require('../middleware/validate');

// Public routes
router.get('/', optionalAuth, blogController.getBlogs);
router.get('/categories', blogController.getCategories);
router.get('/:slug', optionalAuth, blogController.getBlog);

// Protected routes
router.post('/', auth, [
  body('title').notEmpty().withMessage('Title is required'),
  body('content').notEmpty().withMessage('Content is required'),
  body('status').optional().isIn(['draft', 'published'])
], validate, blogController.createBlog);

router.put('/:id', auth, [
  body('title').optional().notEmpty(),
  body('content').optional().notEmpty(),
  body('status').optional().isIn(['draft', 'published'])
], validate, blogController.updateBlog);

router.delete('/:id', auth, blogController.deleteBlog);

module.exports = router;
