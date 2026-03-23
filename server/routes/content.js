const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const contentController = require('../controllers/contentController');
const { auth, adminOnly, optionalAuth } = require('../middleware/auth');
const { uploadImage, handleUploadError } = require('../middleware/upload');
const validate = require('../middleware/validate');

// ========== SITE CONTENT ==========

// Public routes
router.get('/site', contentController.getAllContent);
router.get('/site/:key', contentController.getContent);

// Admin routes
router.put('/site', auth, adminOnly, [
  body('key').notEmpty().withMessage('Key is required'),
  body('value').notEmpty().withMessage('Value is required'),
  body('type').optional().isIn(['text', 'html', 'json', 'image'])
], validate, contentController.updateContent);

router.put('/site/bulk', auth, adminOnly, [
  body('items').isArray().withMessage('Items array required')
], validate, contentController.bulkUpdateContent);

// ========== TEAM MEMBERS ==========

router.get('/team', optionalAuth, contentController.getTeamMembers);

router.post('/team', auth, adminOnly,
  uploadImage.single('photo'),
  handleUploadError,
  [
    body('name').notEmpty().trim().withMessage('Name is required'),
    body('position').notEmpty().trim().withMessage('Position is required'),
    body('bio').optional().trim(),
    body('email').optional().isEmail(),
    body('displayOrder').optional().isNumeric()
  ],
  validate,
  contentController.createTeamMember
);

router.put('/team/:id', auth, adminOnly,
  uploadImage.single('photo'),
  handleUploadError,
  [
    body('name').optional().notEmpty().trim(),
    body('position').optional().notEmpty().trim(),
    body('bio').optional().trim(),
    body('email').optional().isEmail(),
    body('displayOrder').optional().isNumeric()
  ],
  validate,
  contentController.updateTeamMember
);

router.delete('/team/:id', auth, adminOnly, contentController.deleteTeamMember);

// ========== EVENTS ==========

router.get('/events', optionalAuth, contentController.getEvents);
router.get('/events/:slug', contentController.getEvent);
router.get('/events/id/:id', auth, adminOnly, contentController.getEventById);

router.post('/events', auth, adminOnly,
  uploadImage.single('featuredImage'),
  handleUploadError,
  [
    body('title').notEmpty().trim().withMessage('Title is required'),
    body('eventDate').isISO8601().withMessage('Valid date required'),
    body('description').optional().trim(),
    body('location').optional().trim(),
    body('status').optional().isIn(['upcoming', 'ongoing', 'completed', 'cancelled'])
  ],
  validate,
  contentController.createEvent
);

router.put('/events/:id', auth, adminOnly,
  uploadImage.single('featuredImage'),
  handleUploadError,
  [
    body('title').optional().notEmpty().trim(),
    body('eventDate').optional().isISO8601(),
    body('description').optional().trim(),
    body('status').optional().isIn(['upcoming', 'ongoing', 'completed', 'cancelled'])
  ],
  validate,
  contentController.updateEvent
);

router.delete('/events/:id', auth, adminOnly, contentController.deleteEvent);

// ========== CAUSES ==========

router.get('/causes', optionalAuth, contentController.getCauses);
router.get('/causes/:slug', contentController.getCause);
router.get('/causes/id/:id', auth, adminOnly, contentController.getCauseById);

router.post('/causes', auth, adminOnly,
  uploadImage.single('featuredImage'),
  handleUploadError,
  [
    body('title').notEmpty().trim().withMessage('Title is required'),
    body('description').optional().trim(),
    body('category').optional().trim(),
    body('goalAmount').optional().isNumeric(),
    body('status').optional().isIn(['active', 'completed', 'paused'])
  ],
  validate,
  contentController.createCause
);

router.put('/causes/:id', auth, adminOnly,
  uploadImage.single('featuredImage'),
  handleUploadError,
  [
    body('title').optional().notEmpty().trim(),
    body('description').optional().trim(),
    body('category').optional().trim(),
    body('goalAmount').optional().isNumeric(),
    body('raisedAmount').optional().isNumeric(),
    body('status').optional().isIn(['active', 'completed', 'paused'])
  ],
  validate,
  contentController.updateCause
);

router.delete('/causes/:id', auth, adminOnly, contentController.deleteCause);

module.exports = router;
