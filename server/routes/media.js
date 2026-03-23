const express = require('express');
const router = express.Router();
const { uploadFile, getMedia, deleteMedia } = require('../controllers/mediaController');
const { auth, adminOnly } = require('../middleware/auth');
const { uploadMedia, handleUploadError } = require('../middleware/upload');

router.post('/', auth, adminOnly, uploadMedia.single('file'), handleUploadError, uploadFile);
router.get('/', auth, adminOnly, getMedia);
router.delete('/:id', auth, adminOnly, deleteMedia);

module.exports = router;
