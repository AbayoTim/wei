const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const contactController = require('../controllers/contactController');
const { auth, adminOnly } = require('../middleware/auth');
const validate = require('../middleware/validate');
const honeypot = require('../middleware/honeypot');

// Public route
router.post('/', honeypot, [
  body('firstName').notEmpty().trim().isLength({ max: 100 }).withMessage('First name is required'),
  body('lastName').notEmpty().trim().isLength({ max: 100 }).withMessage('Last name is required'),
  body('email').isEmail().normalizeEmail().withMessage('Valid email is required'),
  body('phone').optional().trim().isLength({ max: 30 }),
  body('subject').optional().trim().isLength({ max: 200 }),
  body('message').notEmpty().isLength({ max: 5000 }).withMessage('Message is required')
], validate, contactController.submitContact);

// Admin routes
router.get('/', auth, adminOnly, contactController.getContacts);
router.get('/stats', auth, adminOnly, contactController.getStats);
router.get('/:id', auth, adminOnly, contactController.getContact);
router.put('/:id', auth, adminOnly, [
  body('status').isIn(['new', 'read', 'replied', 'archived']),
  body('replyMessage').optional()
], validate, contactController.updateContact);
router.delete('/:id', auth, adminOnly, contactController.deleteContact);

module.exports = router;
