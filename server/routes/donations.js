const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const donationController = require('../controllers/donationController');
const { auth, adminOnly } = require('../middleware/auth');
const { uploadReceipt, handleUploadError } = require('../middleware/upload');
const validate = require('../middleware/validate');
const honeypot = require('../middleware/honeypot');

// Public routes
router.post('/',
  uploadReceipt.single('receipt'),  // multer must run first to populate req.body for multipart
  handleUploadError,
  honeypot,                          // honeypot check after req.body is available
  [
    body('donorName').notEmpty().trim().isLength({ max: 100 }).withMessage('Name is required'),
    body('donorEmail').isEmail().normalizeEmail().withMessage('Valid email is required'),
    body('donorPhone').optional().trim().isLength({ max: 30 }),
    body('amount').isNumeric().withMessage('Valid amount is required'),
    body('currency').optional().isIn(['TZS', 'USD', 'EUR', 'GBP']),
    body('paymentMethod').optional().trim().isLength({ max: 100 }),
    body('transactionReference').optional().trim().isLength({ max: 200 }),
    body('message').optional().trim().isLength({ max: 2000 }),
    body('cause').optional().trim().isLength({ max: 200 }),
    body('isAnonymous').optional().isBoolean()
  ],
  validate,
  donationController.submitDonation
);

router.get('/approved', donationController.getApprovedDonations);

// Admin routes
router.get('/', auth, adminOnly, donationController.getDonations);
router.get('/stats', auth, adminOnly, donationController.getStats);
router.get('/receipt/:filename', auth, adminOnly, donationController.getReceipt);
router.get('/:id', auth, adminOnly, donationController.getDonation);
router.post('/:id/approve', auth, adminOnly, donationController.approveDonation);
router.post('/:id/reject', auth, adminOnly, [
  body('reason').optional().trim()
], validate, donationController.rejectDonation);

module.exports = router;
