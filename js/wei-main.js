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
