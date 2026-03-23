const { Media } = require('../models');
const fs = require('fs');
const path = require('path');

exports.uploadFile = async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({ success: false, message: 'No file uploaded' });
    }

    const { file } = req;
    let type = 'document';
    if (file.mimetype.startsWith('image/')) type = 'image';
    else if (file.mimetype.startsWith('video/')) type = 'video';

    const subdir = type === 'image' ? 'images' : type === 'video' ? 'videos' : 'docs';
    const url = `/uploads/${subdir}/${file.filename}`;

    const media = await Media.create({
      filename: file.filename,
      originalName: file.originalname,
      mimetype: file.mimetype,
      size: file.size,
      type,
      url,
      uploadedBy: req.userId
    });

    res.status(201).json({ success: true, data: media });
  } catch (error) {
    console.error('Upload error:', error);
    res.status(500).json({ success: false, message: 'Server error' });
  }
};

exports.getMedia = async (req, res) => {
  try {
    const { type } = req.query;
    const where = {};
    if (type) where.type = type;
    const media = await Media.findAll({ where, order: [['createdAt', 'DESC']] });
    res.json({ success: true, data: media });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Server error' });
  }
};

exports.deleteMedia = async (req, res) => {
  try {
    const { id } = req.params;
    const media = await Media.findByPk(id);
    if (!media) return res.status(404).json({ success: false, message: 'Media not found' });

    const relativePath = media.url.replace('/uploads/', '');
    const filepath = path.join(__dirname, '../uploads', relativePath);
    if (fs.existsSync(filepath)) fs.unlinkSync(filepath);

    await media.destroy();
    res.json({ success: true, message: 'Media deleted' });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Server error' });
  }
};
