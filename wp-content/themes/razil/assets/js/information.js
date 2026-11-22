/* Information pages: on-demand panoramas, document outline and public details. */
(() => {
  'use strict';
  async function copyText(value, button) {
    if (window.isSecureContext && navigator.clipboard) {
      try {
        await navigator.clipboard.writeText(value);
        return true;
      } catch {
        // Some browsers restrict the clipboard API while allowing selection-based copying.
      }
    }
    const field = document.createElement('textarea');
    field.value = value;
    field.readOnly = true;
    field.style.cssText = 'position:fixed;top:0;left:-9999px';
    document.body.append(field);
    try {
      field.select();
      field.setSelectionRange(0, value.length);
      return document.execCommand('copy');
    } catch {
      return false;
    } finally {
      field.remove();
      button.focus({ preventScroll: true });
    }
  }

  function selectText(element) {
    const range = document.createRange();
    range.selectNodeContents(element);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  }

  function enableAddressCopy(button, address, status) {
    let feedbackTimer;
    button.addEventListener('click', async () => {
      const copied = await copyText(address, button);
      window.clearTimeout(feedbackTimer);
      if (copied) {
        status.textContent = 'Адрес скопирован.';
        feedbackTimer = window.setTimeout(() => { status.textContent = ''; }, 3000);
        return;
      }
      const selected = document.createElement('span');
      selected.textContent = address;
      status.replaceChildren('Скопируйте адрес вручную: ', selected);
      selectText(selected);
    });
  }

  const address = document.getElementById('office-address')?.textContent.trim();
  if (address) {
    document.querySelectorAll('.rz-contact-address').forEach(label => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'rz-address-trigger';
      button.title = 'Скопировать адрес';
      button.setAttribute('aria-label', 'Скопировать адрес: ' + address);
      button.append(...label.childNodes);
      label.append(button);
      const status = document.createElement('span');
      status.className = 'rz-address-feedback';
      status.setAttribute('role', 'status');
      label.after(status);
      enableAddressCopy(button, address, status);
    });
  }
  // Contacts saved with the previous layout have a separate copy control.
  document.querySelectorAll('[data-copy-address]').forEach(button => {
    const status = button.parentElement.querySelector('[data-copy-status]');
    if (status) enableAddressCopy(button, button.dataset.copyAddress, status);
  });
  document.querySelectorAll('[data-rz-tour]').forEach(tour => {
    const start = tour.querySelector('[data-tour-start]');
    const cover = tour.querySelector('.rz-tour__cover');
    const viewer = tour.querySelector('.rz-tour__viewer');
    const frame = tour.querySelector('.rz-tour__frame');
    const scene = tour.querySelector('[data-tour-scene]');
    const close = tour.querySelector('[data-tour-close]');
    const fullscreen = tour.querySelector('[data-tour-fullscreen]');
    const status = tour.querySelector('[data-tour-status]');
    let timeout;
    let iframe;
    function load() {
      clearTimeout(timeout);
      const url = new URL(tour.dataset.tourUrl, location.href);
      url.searchParams.set('s', scene.value);
      url.searchParams.set('norotation', '');
      frame.replaceChildren();
      iframe = document.createElement('iframe');
      iframe.title = 'Виртуальный тур: ' + scene.options[scene.selectedIndex].text;
      iframe.allow = 'fullscreen';
      iframe.allowFullscreen = true;
      status.textContent = 'Открываем панораму…';
      iframe.addEventListener('load', () => {
        clearTimeout(timeout);
        status.textContent = 'Перетаскивайте изображение, чтобы осмотреться. Стрелки внутри панорамы ведут в соседние помещения.';
      });
      iframe.src = url.href;
      frame.append(iframe);
      timeout = setTimeout(() => { status.textContent = 'Панорама загружается дольше обычного. Можно открыть тур отдельно по ссылке рядом.'; }, 15000);
    }
    start.addEventListener('click', e => {
      e.preventDefault();
      cover.hidden = true;
      viewer.hidden = false;
      load();
      close.focus({ preventScroll: true });
    });
    scene.addEventListener('change', load);
    close.addEventListener('click', async () => {
      if (document.fullscreenElement === viewer) await document.exitFullscreen().catch(() => {});
      clearTimeout(timeout);
      frame.replaceChildren();
      viewer.hidden = true;
      cover.hidden = false;
      status.textContent = 'Осмотритесь до посещения: перемещайтесь по стрелкам внутри панорамы.';
      start.focus({ preventScroll: true });
    });
    if (document.fullscreenEnabled && viewer.requestFullscreen) fullscreen.hidden = false;
    fullscreen.addEventListener('click', async () => {
      try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else await viewer.requestFullscreen();
      } catch (_) { status.textContent = 'Полноэкранный режим недоступен. Можно открыть тур отдельно.'; }
    });
    document.addEventListener('fullscreenchange', () => {
      fullscreen.textContent = document.fullscreenElement === viewer ? 'Свернуть' : 'На весь экран';
    });
  });

  document.querySelectorAll('.rz-document-toc details').forEach(details => {
    details.open = matchMedia('(min-width: 64rem)').matches;
  });

  const reviewList = document.querySelector('.rz-reviews-page .rz-reviews-list__items');
  if (reviewList) {
    const reviews = [...reviewList.querySelectorAll('.rz-review__text')].map((text, index) => {
      const button = document.createElement('button');
      text.id = 'review-text-' + (index + 1);
      button.type = 'button';
      button.className = 'rz-review-expand';
      button.textContent = 'Читать полностью';
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-controls', text.id);
      button.hidden = true;
      text.after(button);
      button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!expanded));
        text.classList.toggle('is-collapsed', expanded);
        button.textContent = expanded ? 'Читать полностью' : 'Свернуть отзыв';
      });
      return { text, button };
    });
    function measureReviews() {
      reviews.forEach(({ text, button }) => {
        if (!text.getBoundingClientRect().width || button.getAttribute('aria-expanded') === 'true') return;
        text.classList.add('is-collapsed');
        const truncated = text.scrollHeight > text.clientHeight + 1;
        button.hidden = !truncated;
        if (!truncated) text.classList.remove('is-collapsed');
      });
    }
    new ResizeObserver(measureReviews).observe(reviewList);
    new MutationObserver(records => {
      if (records.some(r => r.target.classList.contains('rz-reviews-list__item'))) measureReviews();
    }).observe(reviewList, { subtree: true, attributes: true, attributeFilter: ['hidden'] });
    document.fonts.ready.then(measureReviews);
  }

  document.querySelectorAll('[data-copy-target]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', async () => {
      const target = document.getElementById(button.dataset.copyTarget);
      if (!target) return;
      const message = document.querySelector('[data-copy-status]');
      if (await copyText(target.textContent.trim(), button)) {
        message.textContent = button.dataset.copyLabel + ': скопировано.';
      } else {
        selectText(target);
        message.textContent = 'Текст выделен. Скопируйте его через меню браузера или сочетанием Ctrl+C.';
      }
    });
  });
  document.querySelectorAll('[data-rz-print]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', () => window.print());
  });
})();
