const { SiteContent, TeamMember, Event, Cause } = require('../models');
const { paginate, formatPaginationResponse, generateSlug } = require('../utils/helpers');
const { Op } = require('sequelize');

// ========== SITE CONTENT ==========

// Get all site content
exports.getAllContent = async (req, res) => {
  try {
    const content = await SiteContent.findAll();

    // Transform to key-value object
    const contentObj = {};
    content.forEach(item => {
      contentObj[item.key] = {
        value: item.value,
        type: item.type
      };
    });

    res.json({
      success: true,
      data: contentObj
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get single content by key
exports.getContent = async (req, res) => {
  try {
    const { key } = req.params;

    const content = await SiteContent.findOne({ where: { key } });

    if (!content) {
      return res.status(404).json({
        success: false,
        message: 'Content not found'
      });
    }

    res.json({
      success: true,
      data: content
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Update or create content (admin)
exports.updateContent = async (req, res) => {
  try {
    const { key, value, type, description } = req.body;

    let content = await SiteContent.findOne({ where: { key } });

    if (content) {
      await content.update({
        value,
        type,
        description,
        lastUpdatedBy: req.userId
      });
    } else {
      content = await SiteContent.create({
        key,
        value,
        type: type || 'text',
        description,
        lastUpdatedBy: req.userId
      });
    }

    res.json({
      success: true,
      message: 'Content updated successfully',
      data: content
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Bulk update content (admin)
exports.bulkUpdateContent = async (req, res) => {
  try {
    const { items } = req.body; // Array of { key, value, type }

    for (const item of items) {
      let content = await SiteContent.findOne({ where: { key: item.key } });

      if (content) {
        await content.update({
          value: item.value,
          type: item.type,
          lastUpdatedBy: req.userId
        });
      } else {
        await SiteContent.create({
          key: item.key,
          value: item.value,
          type: item.type || 'text',
          lastUpdatedBy: req.userId
        });
      }
    }

    res.json({
      success: true,
      message: 'Content updated successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// ========== TEAM MEMBERS ==========

// Get all team members
exports.getTeamMembers = async (req, res) => {
  try {
    const { active } = req.query;
    const isAdmin = req.user && req.user.role === 'admin';

    const where = {};
    if (!isAdmin) {
      where.isActive = true;
    } else if (active !== undefined) {
      where.isActive = active === 'true';
    }

    const members = await TeamMember.findAll({
      where,
      order: [['displayOrder', 'ASC'], ['name', 'ASC']]
    });

    res.json({
      success: true,
      data: members
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Create team member (admin)
exports.createTeamMember = async (req, res) => {
  try {
    const memberData = req.body;

    if (req.file) {
      memberData.photo = req.file.filename;
    }

    const member = await TeamMember.create(memberData);

    res.status(201).json({
      success: true,
      message: 'Team member created successfully',
      data: member
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Update team member (admin)
exports.updateTeamMember = async (req, res) => {
  try {
    const { id } = req.params;
    const updateData = req.body;

    const member = await TeamMember.findByPk(id);

    if (!member) {
      return res.status(404).json({
        success: false,
        message: 'Team member not found'
      });
    }

    if (req.file) {
      updateData.photo = req.file.filename;
    }

    await member.update(updateData);

    res.json({
      success: true,
      message: 'Team member updated successfully',
      data: member
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Delete team member (admin)
exports.deleteTeamMember = async (req, res) => {
  try {
    const { id } = req.params;

    const member = await TeamMember.findByPk(id);

    if (!member) {
      return res.status(404).json({
        success: false,
        message: 'Team member not found'
      });
    }

    await member.destroy();

    res.json({
      success: true,
      message: 'Team member deleted successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// ========== EVENTS ==========

// Get all events
exports.getEvents = async (req, res) => {
  try {
    const { page = 1, limit = 10, status, upcoming } = req.query;
    const isAdmin = req.user && req.user.role === 'admin';

    const where = {};

    if (!isAdmin) {
      where.isPublished = true;
    }

    if (status) {
      where.status = status;
    }

    if (upcoming === 'true') {
      where.eventDate = { [Op.gte]: new Date() };
    }

    const { count, rows } = await Event.findAndCountAll({
      where,
      ...paginate({}, { page, limit }),
      order: [['eventDate', 'ASC']]
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

// Get single event
exports.getEvent = async (req, res) => {
  try {
    const { slug } = req.params;

    const event = await Event.findOne({ where: { slug } });

    if (!event) {
      return res.status(404).json({
        success: false,
        message: 'Event not found'
      });
    }

    res.json({
      success: true,
      data: event
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get single event by ID (admin)
exports.getEventById = async (req, res) => {
  try {
    const event = await Event.findByPk(req.params.id);
    if (!event) return res.status(404).json({ success: false, message: 'Event not found' });
    res.json({ success: true, data: event });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Server error' });
  }
};

// Create event (admin)
exports.createEvent = async (req, res) => {
  try {
    const eventData = req.body;
    eventData.slug = generateSlug(eventData.title);

    if (req.file) {
      eventData.featuredImage = req.file.filename;
    }

    const event = await Event.create(eventData);

    res.status(201).json({
      success: true,
      message: 'Event created successfully',
      data: event
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Update event (admin)
exports.updateEvent = async (req, res) => {
  try {
    const { id } = req.params;
    const updateData = req.body;

    const event = await Event.findByPk(id);

    if (!event) {
      return res.status(404).json({
        success: false,
        message: 'Event not found'
      });
    }

    if (updateData.title && updateData.title !== event.title) {
      updateData.slug = generateSlug(updateData.title);
    }

    if (req.file) {
      updateData.featuredImage = req.file.filename;
    }

    await event.update(updateData);

    res.json({
      success: true,
      message: 'Event updated successfully',
      data: event
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Delete event (admin)
exports.deleteEvent = async (req, res) => {
  try {
    const { id } = req.params;

    const event = await Event.findByPk(id);

    if (!event) {
      return res.status(404).json({
        success: false,
        message: 'Event not found'
      });
    }

    await event.destroy();

    res.json({
      success: true,
      message: 'Event deleted successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// ========== CAUSES ==========

// Get all causes
exports.getCauses = async (req, res) => {
  try {
    const { page = 1, limit = 10, category, featured } = req.query;
    const isAdmin = req.user && req.user.role === 'admin';

    const where = {};

    if (!isAdmin) {
      where.isPublished = true;
    }

    if (category) {
      where.category = category;
    }

    if (featured === 'true') {
      where.isFeatured = true;
    }

    const { count, rows } = await Cause.findAndCountAll({
      where,
      ...paginate({}, { page, limit }),
      order: [['isFeatured', 'DESC'], ['createdAt', 'DESC']]
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

// Get single cause
exports.getCause = async (req, res) => {
  try {
    const { slug } = req.params;

    const cause = await Cause.findOne({ where: { slug } });

    if (!cause) {
      return res.status(404).json({
        success: false,
        message: 'Cause not found'
      });
    }

    res.json({
      success: true,
      data: cause
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Get single cause by ID (admin)
exports.getCauseById = async (req, res) => {
  try {
    const cause = await Cause.findByPk(req.params.id);
    if (!cause) return res.status(404).json({ success: false, message: 'Cause not found' });
    res.json({ success: true, data: cause });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Server error' });
  }
};

// Create cause (admin)
exports.createCause = async (req, res) => {
  try {
    const causeData = req.body;
    causeData.slug = generateSlug(causeData.title);

    if (req.file) {
      causeData.featuredImage = req.file.filename;
    }

    const cause = await Cause.create(causeData);

    res.status(201).json({
      success: true,
      message: 'Cause created successfully',
      data: cause
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Update cause (admin)
exports.updateCause = async (req, res) => {
  try {
    const { id } = req.params;
    const updateData = req.body;

    const cause = await Cause.findByPk(id);

    if (!cause) {
      return res.status(404).json({
        success: false,
        message: 'Cause not found'
      });
    }

    if (updateData.title && updateData.title !== cause.title) {
      updateData.slug = generateSlug(updateData.title);
    }

    if (req.file) {
      updateData.featuredImage = req.file.filename;
    }

    await cause.update(updateData);

    res.json({
      success: true,
      message: 'Cause updated successfully',
      data: cause
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};

// Delete cause (admin)
exports.deleteCause = async (req, res) => {
  try {
    const { id } = req.params;

    const cause = await Cause.findByPk(id);

    if (!cause) {
      return res.status(404).json({
        success: false,
        message: 'Cause not found'
      });
    }

    await cause.destroy();

    res.json({
      success: true,
      message: 'Cause deleted successfully'
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: 'Server error'
    });
  }
};
