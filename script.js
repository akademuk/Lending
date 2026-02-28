/* =============================================
   BREWMIST — Premium Landing Page Scripts
   ============================================= */

;(function () {
  'use strict';

  /* ── DOM CACHE ── */
  document.documentElement.classList.remove('no-js');
  const header = document.getElementById('header');
  const burger = document.querySelector('.header__burger');
  const form = document.getElementById('contactForm');

  /* ─────────────────────────────────────────────
     TELEGRAM BOT CONFIG
     Replace with your actual bot token and chat ID
     ───────────────────────────────────────────── */
  const TG_BOT_TOKEN = 'YOUR_BOT_TOKEN';   // e.g. '123456:ABC-DEF...'
  const TG_CHAT_ID   = 'YOUR_CHAT_ID';     // e.g. '-1001234567890'

  /* ── MOBILE NAV ── */
  function initMobileNav() {
    if (!burger) return;

    const nav = document.querySelector('.header__nav');
    if (!nav) return;

    const mobileNavEl = nav.cloneNode(true);
    mobileNavEl.classList.add('header__nav--mobile');
    mobileNavEl.classList.remove('header__nav');
    document.body.appendChild(mobileNavEl);

    const overlay = document.createElement('div');
    overlay.className = 'mobile-overlay';
    document.body.appendChild(overlay);

    function openMenu() {
      burger.classList.add('header__burger--active');
      burger.setAttribute('aria-expanded', 'true');
      mobileNavEl.classList.add('open');
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
      burger.classList.remove('header__burger--active');
      burger.setAttribute('aria-expanded', 'false');
      mobileNavEl.classList.remove('open');
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }

    burger.addEventListener('click', () => {
      mobileNavEl.classList.contains('open') ? closeMenu() : openMenu();
    });

    overlay.addEventListener('click', closeMenu);

    mobileNavEl.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && mobileNavEl.classList.contains('open')) closeMenu();
    });
  }

  /* ── HEADER SCROLL ── */
  function initHeaderScroll() {
    if (!header) return;
    let ticking = false;
    window.addEventListener('scroll', () => {
      if (!ticking) {
        requestAnimationFrame(() => {
          header.classList.toggle('header--scrolled', window.scrollY > 60);
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  }

  /* ── SMOOTH SCROLL ── */
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', (e) => {
        const target = document.querySelector(anchor.getAttribute('href'));
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  /* ── SCROLL REVEAL ── */
  function initReveal() {
    const items = document.querySelectorAll('.reveal-item');
    if (!items.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const siblings = entry.target.parentElement.querySelectorAll('.reveal-item');
          const idx = Array.from(siblings).indexOf(entry.target);
          entry.target.style.transitionDelay = `${idx * 0.08}s`;
          entry.target.classList.add('revealed');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '0px 0px -40px 0px'
    });

    items.forEach(item => observer.observe(item));
  }

  /* ── PHONE MASK (+380 XX XXX XX XX) ── */
  function initPhoneMask() {
    const phoneInput = document.getElementById('userPhone');
    if (!phoneInput) return;

    phoneInput.addEventListener('focus', () => {
      if (!phoneInput.value) {
        phoneInput.value = '+380 ';
      }
    });

    phoneInput.addEventListener('input', (e) => {
      let raw = phoneInput.value.replace(/\D/g, '');

      // Ensure starts with 380
      if (!raw.startsWith('380')) {
        if (raw.startsWith('80')) raw = '3' + raw;
        else if (raw.startsWith('0')) raw = '38' + raw;
        else if (!raw.startsWith('3')) raw = '380' + raw;
      }

      // Limit to 12 digits (380 + 9 digits)
      raw = raw.slice(0, 12);

      // Format: +380 XX XXX XX XX
      let formatted = '+380';
      const after380 = raw.slice(3);

      if (after380.length > 0) formatted += ' ' + after380.slice(0, 2);
      if (after380.length > 2) formatted += ' ' + after380.slice(2, 5);
      if (after380.length > 5) formatted += ' ' + after380.slice(5, 7);
      if (after380.length > 7) formatted += ' ' + after380.slice(7, 9);

      phoneInput.value = formatted;
    });

    phoneInput.addEventListener('keydown', (e) => {
      // Prevent deleting the +380 prefix
      if ((e.key === 'Backspace' || e.key === 'Delete') && phoneInput.value.length <= 5) {
        e.preventDefault();
      }
    });
  }

  /* ── SEND TO TELEGRAM ── */
  async function sendToTelegram(data) {
    if (TG_BOT_TOKEN === 'YOUR_BOT_TOKEN') {
      console.warn('⚠️ Telegram bot token not configured. Set TG_BOT_TOKEN and TG_CHAT_ID.');
      return true; // Return true so form still shows success
    }

    const text = [
      '☕ *Нова заявка з Brewmist*',
      '',
      `👤 *Ім'я:* ${data.name}`,
      `📞 *Телефон:* ${data.phone}`,
      data.company ? `🏢 *Компанія:* ${data.company}` : '',
      `📊 *Обсяг напоїв/день:* ${data.volume || '—'}`,
      '',
      `🕐 _${new Date().toLocaleString('uk-UA')}_`
    ].filter(Boolean).join('\n');

    try {
      const resp = await fetch(`https://api.telegram.org/bot${TG_BOT_TOKEN}/sendMessage`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          chat_id: TG_CHAT_ID,
          text: text,
          parse_mode: 'Markdown'
        })
      });
      return resp.ok;
    } catch (err) {
      console.error('Telegram send error:', err);
      return false;
    }
  }

  /* ── SEND TO EMAIL (via mailto fallback) ── */
  function sendEmailFallback(data) {
    // This creates a mailto link as a backup. For real email sending,
    // integrate with a backend service (e.g. EmailJS, Formspree, etc.)
    const subject = encodeURIComponent('Нова заявка Brewmist');
    const body = encodeURIComponent(
      `Ім'я: ${data.name}\nТелефон: ${data.phone}\nКомпанія: ${data.company}\nОбсяг: ${data.volume}`
    );
    // Uncomment to auto-open email client:
    // window.location.href = `mailto:your@email.com?subject=${subject}&body=${body}`;
  }

  /* ── FORM VALIDATION & SUBMIT ── */
  function initForm() {
    if (!form) return;

    const nameInput = form.querySelector('#userName');
    const phoneInput = form.querySelector('#userPhone');
    const phoneRegex = /^(\+?38)?0\d{9}$/;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      let valid = true;

      clearErrors();

      // Name validation
      if (!nameInput.value.trim()) {
        showError(nameInput);
        valid = false;
      }

      // Phone validation
      const cleanPhone = phoneInput.value.replace(/[\s\-\(\)\+]/g, '');
      if (!phoneRegex.test(cleanPhone)) {
        showError(phoneInput);
        valid = false;
      }

      if (valid) {
        const submitBtn = form.querySelector('.contact-form__submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Надсилаємо...';

        const volume = form.querySelector('input[name="volume"]:checked');
        const data = {
          name: nameInput.value.trim(),
          phone: phoneInput.value.trim(),
          company: form.querySelector('#userCompany')?.value.trim() || '',
          volume: volume ? volume.value : ''
        };

        // Send to Telegram
        await sendToTelegram(data);

        // Email fallback
        sendEmailFallback(data);

        console.log('📧 Form submitted:', data);

        // Redirect to thank-you page (important for Google Ads conversion tracking)
        window.location.href = 'thanks.html';
      }
    });

    function showError(input) {
      input.classList.add('contact-form__input--error');
      input.addEventListener('input', () => {
        input.classList.remove('contact-form__input--error');
      }, { once: true });
    }

    function clearErrors() {
      form.querySelectorAll('.contact-form__input--error').forEach(el => {
        el.classList.remove('contact-form__input--error');
      });
    }
  }

  /* ── LAZY LOAD MAP ── */
  function initLazyMap() {
    const mapFrame = document.querySelector('.contact__map-frame');
    if (!mapFrame) return;

    const iframe = mapFrame.querySelector('iframe[data-src]');
    if (!iframe || iframe.dataset.loaded) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          iframe.setAttribute('src', iframe.dataset.src);
          iframe.dataset.loaded = 'true';
          observer.unobserve(mapFrame);
        }
      });
    }, { rootMargin: '200px' });

    observer.observe(mapFrame);
  }

  /* ── INIT ── */
  document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initHeaderScroll();
    initSmoothScroll();
    initReveal();
    initPhoneMask();
    initForm();
    initLazyMap();
  });

})();