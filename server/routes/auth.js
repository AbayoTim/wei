const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const authController = require('../controllers/authController');
const { auth, adminOnly } = require('../middleware/auth');
const validate = require('../middleware/validate');

// Login
router.post('/login', [
  body('email').isEmail().normalizeEmail().withMessage('Valid email is required'),
  body('password').notEmpty().withMessage('Password is required')
], validate, authController.login);

// Get current user
router.get('/me', auth, authController.getMe);

// Update password
router.put('/password', auth, [
  body('currentPassword').notEmpty().withMessage('Current password is required'),
  body('newPassword')
    .isLength({ min: 8 })
    .withMessage('New password must be at least 8 characters')
    .matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/)
    .withMessage('Password must contain uppercase, lowercase, and number')
], validate, authController.updatePassword);

// Admin routes
router.post('/users', auth, adminOnly, [
  body('email').isEmail().normalizeEmail().withMessage('Valid email is required'),
  body('password')
    .isLength({ min: 8 })
    .withMessage('Password must be at least 8 characters'),
  body('name').notEmpty().withMessage('Name is required'),
  body('role').optional().isIn(['admin', 'editor']).withMessage('Invalid role')
], validate, authController.createUser);

router.get('/users', auth, adminOnly, authController.getUsers);

router.put('/users/:id', auth, adminOnly, [
  body('email').optional().isEmail().normalizeEmail(),
  body('name').optional().notEmpty(),
  body('role').optional().isIn(['admin', 'editor']),
  body('isActive').optional().isBoolean(),
  body('newPassword').optional().isLength({ min: 6 }).withMessage('Password must be at least 6 characters')
], validate, authController.updateUser);

router.delete('/users/:id', auth, adminOnly, authController.deleteUser);

module.exports = router;
