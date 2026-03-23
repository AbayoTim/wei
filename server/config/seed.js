const { User, SiteContent, Partner, TeamMember, Cause, Event, Blog } = require('../models');

const seedDatabase = async () => {
  try {
    // Create admin user if not exists
    const adminExists = await User.findOne({ where: { email: process.env.ADMIN_EMAIL || 'admin@wei.or.tz' } });

    if (!adminExists) {
      await User.create({
        email: process.env.ADMIN_EMAIL || 'admin@wei.or.tz',
        password: process.env.ADMIN_PASSWORD || 'WeiAdmin2024!',
        name: 'WEI Administrator',
        role: 'admin'
      });
      console.log('Admin user created');
    }

    // Seed site content
    const contentItems = [
      // General
      { key: 'site_name', value: 'Women Empowerment Initiatives', type: 'text', description: 'Site name' },
      { key: 'site_tagline', value: 'Empowering Women, Transforming Communities', type: 'text', description: 'Site tagline' },

      // Contact Info
      { key: 'contact_email', value: 'info@wei.or.tz', type: 'text', description: 'Contact email' },
      { key: 'contact_phone', value: '+255 743 111 867', type: 'text', description: 'Contact phone' },
      { key: 'contact_address', value: 'Dodoma - Makulu, Tanzania', type: 'text', description: 'Physical address' },

      // Social Media
      { key: 'social_twitter', value: 'https://twitter.com/weitanzania', type: 'text', description: 'Twitter URL' },
      { key: 'social_facebook', value: 'https://facebook.com/weitanzania', type: 'text', description: 'Facebook URL' },
      { key: 'social_instagram', value: 'https://instagram.com/weitanzania', type: 'text', description: 'Instagram URL' },

      // About
      { key: 'about_mission', value: 'WEI focuses on enhancing women\'s autonomy and capabilities. We address how women have fewer opportunities for economic participation than men, less access to basic and higher education. Our work emphasizes strengthening women\'s decision-making authority, resource access, and ability to create meaningful change in their lives and communities.', type: 'text', description: 'Mission statement' },
      { key: 'about_vision', value: 'A society where women are empowered, have equal opportunities, and actively participate in all aspects of community development.', type: 'text', description: 'Vision statement' },
      { key: 'about_short', value: 'Women Empowerment Initiatives (WEI) is a non-profit organization based in Dodoma, Tanzania, dedicated to empowering women and transforming communities through sustainable programs in education, health, and economic development.', type: 'text', description: 'Short about text' },

      // Hero Section
      { key: 'hero_title', value: 'Empowering Women, Building Stronger Communities', type: 'text', description: 'Hero section title' },
      { key: 'hero_subtitle', value: 'Join us in creating opportunities for women and youth in Tanzania through education, healthcare, and livelihood programs.', type: 'text', description: 'Hero section subtitle' },

      // CTA
      { key: 'cta_text', value: 'Helping Women, Youth, and Children Build Better Futures', type: 'text', description: 'Call to action text' },

      // Why Choose Us
      { key: 'why_choose_us', value: JSON.stringify([
        {
          title: 'Community-Driven Approach',
          description: 'We work directly with local communities in Dodoma City Council, Chamwino District Council, and Bahi District Council to understand and address their specific needs.'
        },
        {
          title: 'Sustainable Programs',
          description: 'Our programs focus on long-term impact through skills training, education support, and economic empowerment initiatives that create lasting change.'
        },
        {
          title: 'Strong Partnerships',
          description: 'We collaborate with international organizations like ACWW and ViiV Healthcare, as well as government entities to maximize our impact.'
        },
        {
          title: 'Holistic Development',
          description: 'We address multiple aspects of women\'s lives including health, education, economic opportunities, and leadership development.'
        }
      ]), type: 'json', description: 'Why choose us points' }
    ];

    for (const item of contentItems) {
      const exists = await SiteContent.findOne({ where: { key: item.key } });
      if (!exists) {
        await SiteContent.create(item);
      }
    }

    // Seed partners
    const partners = [
      {
        name: 'Associated Country Women of the World (ACWW)',
        description: 'International organization supporting rural women worldwide',
        partnerType: 'funding',
        isActive: true,
        displayOrder: 1
      },
      {
        name: 'ViiV Healthcare',
        description: 'Global specialist HIV company',
        partnerType: 'funding',
        isActive: true,
        displayOrder: 2
      },
      {
        name: 'Dodoma City Council',
        description: 'Local government partner',
        partnerType: 'government',
        isActive: true,
        displayOrder: 3
      },
      {
        name: 'Chamwino District Council',
        description: 'Local government partner',
        partnerType: 'government',
        isActive: true,
        displayOrder: 4
      },
      {
        name: 'Bahi District Council',
        description: 'Local government partner',
        partnerType: 'government',
        isActive: true,
        displayOrder: 5
      }
    ];

    for (const partner of partners) {
      const exists = await Partner.findOne({ where: { name: partner.name } });
      if (!exists) {
        await Partner.create(partner);
      }
    }

    // Seed sample causes
    const causes = [
      {
        title: 'Women Education Initiative',
        slug: 'women-education-initiative',
        description: 'Providing educational opportunities and skills training for women and girls in rural Tanzania.',
        category: 'education',
        goalAmount: 50000000,
        raisedAmount: 15000000,
        currency: 'TZS',
        status: 'active',
        isFeatured: true,
        isPublished: true
      },
      {
        title: 'Healthcare Access Program',
        slug: 'healthcare-access-program',
        description: 'Improving access to healthcare services for women and children in underserved communities.',
        category: 'health',
        goalAmount: 30000000,
        raisedAmount: 8000000,
        currency: 'TZS',
        status: 'active',
        isFeatured: true,
        isPublished: true
      },
      {
        title: 'Economic Empowerment Project',
        slug: 'economic-empowerment-project',
        description: 'Supporting women entrepreneurs with skills training, microfinance, and business mentorship.',
        category: 'livelihood',
        goalAmount: 40000000,
        raisedAmount: 12000000,
        currency: 'TZS',
        status: 'active',
        isFeatured: false,
        isPublished: true
      }
    ];

    for (const cause of causes) {
      const exists = await Cause.findOne({ where: { slug: cause.slug } });
      if (!exists) {
        await Cause.create(cause);
      }
    }

    console.log('Database seeded successfully');
  } catch (error) {
    console.error('Seeding error:', error);
  }
};

module.exports = seedDatabase;
