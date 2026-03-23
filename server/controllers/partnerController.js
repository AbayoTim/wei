const { Partner } = require('../models');
const { paginate, formatPaginationResponse } = require('../utils/helpers');
const path = require('path');
const fs = require('fs');

// Get all partners (public - active only, admin - all)
exports.getPartners = async (req, res) => {
  try {
    const { page = 1, limit = 50, type, active } = req.query;
    const isAdmin = req.user && (req.user.role === 'admin' || req.user.role === 'editor');

    const where = {};

    if (!isAdmin) {
      where.isActive = true;
    } else if (active !== undefined) {
      where.isActive = active === 'true';
    }

    if (type) {
      where.partnerType = type;
    }

    const { count, rows } = await Partner.findAndCountAll({
      where,
      ...paginate({}, { page, limit }),
      order: [['displayOrder', 'ASC'], ['name', 'ASC']]
    });

    res.json({
      success: true,
      ...formatPaginationResponse(rows, count, { page, limit })
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get single partner
exports.getPartner = async (req, res) => {
  try {
    const { id } = req.params;

    const partner = await Partner.findByPk(id);

    if (!partner) {
      return res.status(404).json({
        success: false,
        message: 'Partner not found'
      });
    }

    res.json({
      success: true,
      data: partner
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Create partner (admin)
exports.createPartner = async (req, res) => {
  try {
    const { name, website, description, partnerType, isActive, displayOrder } = req.body;

    let logo = null;
    if (req.file) {
      logo = req.file.filename;
    }

    const partner = await Partner.create({
      name,
      logo,
      website,
      description,
      partnerType,
      isActive: isActive !== 'false',
      displayOrder: displayOrder || 0
    });

    res.status(201).json({
      success: true,
      message: 'Partner created successfully',
      data: partner
    });
  } catch (error) {
    console.error('Create partner error:', error);
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Update partner (admin)
exports.updatePartner = async (req, res) => {
  try {
    const { id } = req.params;
    const { name, website, description, partnerType, isActive, displayOrder } = req.body;

    const partner = await Partner.findByPk(id);

    if (!partner) {
      return res.status(404).json({
        success: false,
        message: 'Partner not found'
      });
    }

    const updateData = {
      name,
      website,
      description,
      partnerType,
      isActive: isActive !== 'false',
      displayOrder: displayOrder || partner.displayOrder
    };

    if (req.file) {
      // Delete old logo
      if (partner.logo) {
        const oldPath = path.join(__dirname, '../uploads/images', partner.logo);
        if (fs.existsSync(oldPath)) {
          fs.unlinkSync(oldPath);
        }
      }
      updateData.logo = req.file.filename;
    }

    await partner.update(updateData);

    res.json({
      success: true,
      message: 'Partner updated successfully',
      data: partner
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Delete partner (admin)
exports.deletePartner = async (req, res) => {
  try {
    const { id } = req.params;

    const partner = await Partner.findByPk(id);

    if (!partner) {
      return res.status(404).json({
        success: false,
        message: 'Partner not found'
      });
    }

    // Delete logo file
    if (partner.logo) {
      const logoPath = path.join(__dirname, '../uploads/images', partner.logo);
      if (fs.existsSync(logoPath)) {
        fs.unlinkSync(logoPath);
      }
    }

    await partner.destroy();

    res.json({
      success: true,
      message: 'Partner deleted successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Reorder partners (admin)
exports.reorderPartners = async (req, res) => {
  try {
    const { orders } = req.body; // Array of { id, displayOrder }

    for (const order of orders) {
      await Partner.update(
        { displayOrder: order.displayOrder },
        { where: { id: order.id } }
      );
    }

    res.json({
      success: true,
      message: 'Partners reordered successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};
