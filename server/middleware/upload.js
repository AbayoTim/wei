const multer = require('multer');
const path = require('path');
const { v4: uuidv4 } = require('uuid');

// Map MIME types to safe extensions — never trust originalname extension
const MIME_TO_EXT = {
  'image/jpeg':  '.jpg',
  'image/png':   '.png',
  'image/gif':   '.gif',
  'image/webp':  '.webp',
  'image/svg+xml': '.svg',
  'video/mp4':   '.mp4',
  'video/webm':  '.webm',
  'video/ogg':   '.ogv',
  'video/quicktime': '.mov',
  'video/x-msvideo': '.avi',
  'application/pdf': '.pdf',
  'application/msword': '.doc',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '.docx',
  'application/vnd.ms-excel': '.xls',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': '.xlsx',
  'application/vnd.ms-powerpoint': '.ppt',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': '.pptx'
};

const safeExt = (file) => MIME_TO_EXT[file.mimetype] || '.bin';

// Storage for receipts
const receiptStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, path.join(__dirname, '../uploads/receipts')),
  filename: (req, file, cb) => cb(null, `${uuidv4()}${safeExt(file)}`)
});

// Storage for images
const imageStorage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, path.join(__dirname, '../uploads/images')),
  filename: (req, file, cb) => cb(null, `${uuidv4()}${safeExt(file)}`)
});

// Dynamic storage for media (images/videos/docs)
const mediaStorage = multer.diskStorage({
  destination: (req, file, cb) => {
    let folder = 'docs';
    if (file.mimetype.startsWith('image/')) folder = 'images';
    else if (file.mimetype.startsWith('video/')) folder = 'videos';
    cb(null, path.join(__dirname, `../uploads/${folder}`));
  },
  filename: (req, file, cb) => cb(null, `${uuidv4()}${safeExt(file)}`)
});

const receiptFilter = (req, file, cb) => {
  const allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
  allowed.includes(file.mimetype)
    ? cb(null, true)
    : cb(new Error('Invalid file type. Only JPEG, PNG, GIF, and PDF files are allowed.'), false);
};

const imageFilter = (req, file, cb) => {
  const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  allowed.includes(file.mimetype)
    ? cb(null, true)
    : cb(new Error('Invalid file type. Only image files are allowed.'), false);
};

const mediaFilter = (req, file, cb) => {
  const allowed = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'
  ];
  allowed.includes(file.mimetype)
    ? cb(null, true)
    : cb(new Error('Invalid file type. Allowed: images, videos (MP4/WebM), documents (PDF/Word/Excel/PPT).'), false);
};

const uploadReceipt = multer({
  storage: receiptStorage,
  fileFilter: receiptFilter,
  limits: { fileSize: 5 * 1024 * 1024 }
});

const uploadImage = multer({
  storage: imageStorage,
  fileFilter: imageFilter,
  limits: { fileSize: 5 * 1024 * 1024 }
});

const uploadMedia = multer({
  storage: mediaStorage,
  fileFilter: mediaFilter,
  limits: { fileSize: 100 * 1024 * 1024 } // 100MB for videos
});

const handleUploadError = (err, req, res, next) => {
  if (err instanceof multer.MulterError) {
    if (err.code === 'LIMIT_FILE_SIZE') {
      return res.status(400).json({ success: false, message: 'File too large.' });
    }
    return res.status(400).json({ success: false, message: err.message });
  }
  if (err) {
    return res.status(400).json({ success: false, message: err.message });
  }
  next();
};

module.exports = { uploadReceipt, uploadImage, uploadMedia, handleUploadError };
