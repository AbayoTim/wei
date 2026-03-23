require('dotenv').config();

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const path = require('path');
const fs = require('fs');

const { syncDatabase } = require('./models');
const seedDatabase = require('./config/seed');

// Import routes
const authRoutes = require('./routes/auth');
const blogRoutes = require('./routes/blog');
const subscriberRoutes = require('./routes/subscribers');
const contactRoutes = require('./routes/contact');
const donationRoutes = require('./routes/donations');
const partnerRoutes = require('./routes/partners');
const contentRoutes = require('./routes/content');
const mediaRoutes = require('./routes/media');

const app = express();

// Ensure required directories exist
const dirs = [
  path.join(__dirname, 'data'),
  path.join(__dirname, 'uploads/receipts'),
  path.join(__dirname, 'uploads/images'),
  path.join(__dirname, 'uploads/videos'),
  path.join(__dirname, 'uploads/docs')
];

dirs.forEach(dir => {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
});

// Security middleware
app.use(helmet({
  crossOriginResourcePolicy: { policy: "cross-origin" },
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc:  ["'self'", "'unsafe-inline'", "cdn.jsdelivr.net"],
      styleSrc:   ["'self'", "'unsafe-inline'", "cdn.jsdelivr.net"],
      imgSrc:     ["'self'", "data:", "blob:"],
      fontSrc:    ["'self'", "cdn.jsdelivr.net"],
      connectSrc: ["'self'"],
      objectSrc:  ["'none'"],
      baseUri:    ["'self'"],
      formAction: ["'self'"],
      frameAncestors: ["'none'"]
    }
  },
  referrerPolicy: { policy: 'strict-origin-when-cross-origin' }
}));

const allowedOrigins = process.env.FRONTEND_URL
  ? process.env.FRONTEND_URL.split(',').map(s => s.trim())
  : [];

app.use(cors({
  origin: (origin, callback) => {
    // Allow same-origin requests (no Origin header) and server-to-server
    if (!origin) return callback(null, true);
    // If no FRONTEND_URL configured, allow all origins
    if (allowedOrigins.length === 0) return callback(null, true);
    if (allowedOrigins.includes(origin)) return callback(null, true);
    callback(new Error(`CORS: origin '${origin}' not allowed`));
  },
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization'],
  credentials: true
}));

// Rate limiting
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, message: 'Too many requests, please try again later.' }
});

const authLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 5,
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, message: 'Too many login attempts, please try again later.' }
});

// Stricter limits for public form submission endpoints
const formLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, message: 'Too many submissions, please try again later.' }
});

const subscribeLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 5,
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, message: 'Too many subscription attempts, please try again later.' }
});

app.use('/api/', limiter);
app.use('/api/auth/login', authLimiter);
app.use('/api/donations', formLimiter);
app.use('/api/contact', formLimiter);
app.use('/api/subscribers/subscribe', subscribeLimiter);

// Body parsing — keep limits tight; multipart handled by multer
app.use(express.json({ limit: '1mb' }));
app.use(express.urlencoded({ extended: true, limit: '1mb' }));

// Static files
app.use('/uploads', express.static(path.join(__dirname, 'uploads'), {
  setHeaders: (res) => {
    res.set('Cross-Origin-Resource-Policy', 'cross-origin');
  }
}));
app.use(express.static(path.join(__dirname, '../')));

// API Routes
app.use('/api/auth', authRoutes);
app.use('/api/blogs', blogRoutes);
app.use('/api/subscribers', subscriberRoutes);
app.use('/api/contact', contactRoutes);
app.use('/api/donations', donationRoutes);
app.use('/api/partners', partnerRoutes);
app.use('/api/content', contentRoutes);
app.use('/api/media', mediaRoutes);

// Health check
app.get('/api/health', (req, res) => {
  res.json({
    success: true,
    message: 'WEI API is running',
    timestamp: new Date().toISOString()
  });
});

// Serve frontend for all other routes
app.get('*', (req, res) => {
  const requestedFile = path.join(__dirname, '../', req.path);

  if (fs.existsSync(requestedFile) && fs.statSync(requestedFile).isFile()) {
    res.sendFile(requestedFile);
  } else {
    res.sendFile(path.join(__dirname, '../index.html'));
  }
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('Error:', err);

  if (err.name === 'SequelizeValidationError') {
    return res.status(400).json({
      success: false,
      message: 'Validation error',
      errors: err.errors.map(e => ({
        field: e.path,
        message: e.message
      }))
    });
  }

  if (err.name === 'SequelizeUniqueConstraintError') {
    return res.status(400).json({
      success: false,
      message: 'Duplicate entry',
      errors: err.errors.map(e => ({
        field: e.path,
        message: `${e.path} already exists`
      }))
    });
  }

  res.status(500).json({
    success: false,
    message: process.env.NODE_ENV === 'development'
      ? err.message
      : 'Internal server error'
  });
});

// Start server
const PORT = process.env.PORT || 3000;

const startServer = async () => {
  try {
    // Sync database
    await syncDatabase(false);

    // Seed initial data
    await seedDatabase();

    app.listen(PORT, '0.0.0.0', () => {
      const adminEmail    = process.env.ADMIN_EMAIL    || 'admin@wei.or.tz';
      const adminPassword = process.env.ADMIN_PASSWORD || 'WeiAdmin2024!';
      console.log(`
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║   Women Empowerment Initiatives (WEI) Server               ║
║                                                            ║
║   Server running on port ${PORT}                              ║
║   Environment: ${process.env.NODE_ENV || 'development'}                            ║
║                                                            ║
║   API:     http://localhost:${PORT}/api                       ║
║   Website: http://localhost:${PORT}                           ║
║   Admin:   http://localhost:${PORT}/admin                     ║
║                                                            ║
║   Admin login                                              ║
║   Email:    ${adminEmail.padEnd(44)}║
║   Password: ${adminPassword.padEnd(44)}║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
      `);
    });
  } catch (error) {
    console.error('Failed to start server:', error);
    process.exit(1);
  }
};

startServer();

module.exports = app;
