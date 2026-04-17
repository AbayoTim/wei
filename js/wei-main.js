/**
 * WEI Tanzania - Main JavaScript
 * Women Empowerment Initiatives
 * Copyright (c) Women Empowerment Initiatives (WEI). All rights reserved.
 * Original work - no third-party template.
 */

(function () {
  'use strict';

  /* ============================================================
     NAVBAR: scroll behaviour
     ============================================================ */
  var navbar = document.querySelector('.wei-navbar');
  if (navbar) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > 60) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });
  }

  /* ============================================================
     MOBILE MENU
     ============================================================ */
  var menuToggle   = document.querySelector('.wei-menu-toggle');
  var mobileNav    = document.querySelector('.wei-mobile-nav');
  var mobileClose  = document.querySelector('.wei-mobile-nav-close');
  var overlayBg    = document.querySelector('.wei-overlay-bg');

  function openMobileMenu() {
    if (mobileNav)  mobileNav.classList.add('open');
    if (overlayBg)  overlayBg.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileMenu() {
    if (mobileNav)  mobileNav.classList.remove('open');
    if (overlayBg)  overlayBg.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (menuToggle) menuToggle.addEventListener('click', openMobileMenu);
  if (mobileClose) mobileClose.addEventListener('click', closeMobileMenu);
  if (overlayBg) overlayBg.addEventListener('click', closeMobileMenu);

  /* ============================================================
     HERO SLIDER
     ============================================================ */
  var slides = document.querySelectorAll('.wei-hero-slide');
  var dots   = document.querySelectorAll('.wei-hero-dot');
  var current = 0;
  var sliderInterval;

  function goToSlide(index) {
    if (!slides.length) return;
    slides[current].classList.remove('active');
    dots[current] && dots[current].classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current] && dots[current].classList.add('active');
  }

  function startSlider() {
    if (slides.length <= 1) return;
    sliderInterval = setInterval(function () {
      goToSlide(current + 1);
    }, 5000);
  }

  if (slides.length > 0) {
    goToSlide(0);
    startSlider();
    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () {
        clearInterval(sliderInterval);
        goToSlide(i);
        startSlider();
      });
    });
  }

  window.reinitHeroSlider = function () {
    clearInterval(sliderInterval);
    slides  = document.querySelectorAll('.wei-hero-slide');
    dots    = document.querySelectorAll('.wei-hero-dot');
    current = 0;
    if (slides.length > 0) {
      goToSlide(0);
      startSlider();
      dots.forEach(function (dot, i) {
        dot.addEventListener('click', function () {
          clearInterval(sliderInterval);
          goToSlide(i);
          startSlider();
        });
      });
    }
  };

  /* ============================================================
     SMOOTH SCROLL for anchor links
     ============================================================ */
  document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        closeMobileMenu();
      }
    });
  });

})();

/* ============================================================
   SITE-WIDE SETTINGS — runs on every public page
   Updates header/footer social links, footer contact info,
   and footer tagline from /api/content/site
   ============================================================ */
(async function applySiteSettings() {
  try {
    var res  = await fetch('/api/content/site');
    var json = await res.json();
    if (!json.success) return;
    var c = json.data;

    // Helper: safe text reconstruction for icon+text paragraphs
    function setIconText(p, value) {
      var icon = p.querySelector('i');
      if (!icon) return;
      var cls = icon.className;
      p.innerHTML = '<i class="' + cls + '"></i> ' + value;
    }

    // Social links in header (.wei-header-social) and footer (.wei-footer-social a)
    var SOCIAL = {
      'bi-facebook':  'social_facebook',
      'bi-twitter-x': 'social_twitter',
      'bi-twitter':   'social_twitter',
      'bi-instagram': 'social_instagram',
      'bi-youtube':   'social_youtube',
      'bi-linkedin':  'social_linkedin',
    };
    document.querySelectorAll('a.wei-header-social, .wei-footer-social a').forEach(function (a) {
      var icon = a.querySelector('i');
      if (!icon) return;
      for (var iconCls in SOCIAL) {
        var key = SOCIAL[iconCls];
        if (icon.classList.contains(iconCls) && c[key] && c[key].value) {
          a.href = c[key].value;
          break;
        }
      }
    });

    // Footer contact info (.wei-footer-contact paragraphs)
    var footerContact = document.querySelector('.wei-footer-contact');
    if (footerContact) {
      footerContact.querySelectorAll('p').forEach(function (p) {
        var icon = p.querySelector('i');
        if (!icon) return;
        if (icon.classList.contains('bi-geo-alt')   && c.contact_address && c.contact_address.value) setIconText(p, c.contact_address.value);
        if (icon.classList.contains('bi-telephone') && c.contact_phone   && c.contact_phone.value)   setIconText(p, c.contact_phone.value);
        if (icon.classList.contains('bi-envelope')  && c.contact_email   && c.contact_email.value)   setIconText(p, c.contact_email.value);
      });
    }

    // Footer about description — first <p> in the first .col-md-3 of the footer
    if (c.site_tagline && c.site_tagline.value) {
      var firstCol = document.querySelector('.wei-footer .row > .col-md-3:first-child');
      if (firstCol) {
        var p = firstCol.querySelector('p');
        if (p) p.textContent = c.site_tagline.value;
      }
    }
  } catch (e) {}
})();
