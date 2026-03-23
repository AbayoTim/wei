const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const subscriberController = require('../controllers/subscriberController');
const { auth, adminOnly } = require('../middleware/auth');
const validate = require('../middleware/validate');
const honeypot = require('../middleware/honeypot');

// Public routes
router.post('/subscribe', honeypot, [
  body('email').isEmail().normalizeEmail().withMessage('Valid email is required'),
  body('name').optional().trim().isLength({ max: 100 })
], validate, subscriberController.subscribe);

router.get('/confirm/:token', subscriberController.confirmSubscription);
router.get('/unsubscribe/:token', subscriberController.unsubscribe);

// Admin routes
router.get('/', auth, adminOnly, subscriberController.getSubscribers);
router.get('/stats', auth, adminOnly, subscriberController.getStats);
router.get('/export', auth, adminOnly, subscriberController.exportSubscribers);
router.delete('/:id', auth, adminOnly, subscriberController.deleteSubscriber);

module.exports = router;
