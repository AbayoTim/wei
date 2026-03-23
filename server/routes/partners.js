const express = require('express');
const router = express.Router();
const { body } = require('express-validator');
const partnerController = require('../controllers/partnerController');
const { auth, adminOnly, optionalAuth } = require('../middleware/auth');
const { uploadImage, handleUploadError } = require('../middleware/upload');
const validate = require('../middleware/validate');

// Public routes
router.get('/', optionalAuth, partnerController.getPartners);
router.get('/:id', partnerController.getPartner);

// Admin routes
router.post('/', auth, adminOnly,
  uploadImage.single('logo'),
  handleUploadError,
  [
    body('name').notEmpty().trim().withMessage('Name is required'),
    body('website').optional().isURL().withMessage('Valid URL required'),
    body('description').optional().trim(),
    body('partnerType').optional().isIn(['funding', 'implementing', 'government', 'other']),
    body('isActive').optional(),
    body('displayOrder').optional().isNumeric()
  ],
  validate,
  partnerController.createPartner
);

router.put('/:id', auth, adminOnly,
  uploadImage.single('logo'),
  handleUploadError,
  [
    body('name').optional().notEmpty().trim(),
    body('website').optional().isURL(),
    body('description').optional().trim(),
    body('partnerType').optional().isIn(['funding', 'implementing', 'government', 'other']),
    body('isActive').optional(),
    body('displayOrder').optional().isNumeric()
  ],
  validate,
  partnerController.updatePartner
);

router.delete('/:id', auth, adminOnly, partnerController.deletePartner);

router.post('/reorder', auth, adminOnly, [
  body('orders').isArray().withMessage('Orders array required')
], validate, partnerController.reorderPartners);

module.exports = router;
