(() => {
  'use strict';

  const drawer = document.getElementById('cartDrawer');
  const overlay = document.getElementById('cartDrawerOverlay');
  const body = document.getElementById('cartDrawerBody');
  const drawerFooter = document.getElementById('cartDrawerFooter');
  const toast = document.getElementById('cartToast');

  if (!drawer || !overlay || !body || !drawerFooter) {
    return;
  }

  const summaryUrl = drawer.dataset.summaryUrl || 'cart-drawer-api.php';
  let lastFocusedElement = null;
  let toastTimer = 0;
  let requestInProgress = false;

  const escapeHtml = value => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const showToast = (message, type = 'success') => {
    if (!toast) return;

    window.clearTimeout(toastTimer);
    toast.textContent = message;
    toast.className = `cart-toast show ${type}`;

    toastTimer = window.setTimeout(() => {
      toast.classList.remove('show');
    }, 2800);
  };

  const updateBadges = count => {
    const safeCount = Math.max(0, Number(count) || 0);

    document.querySelectorAll('[data-cart-count]').forEach(badge => {
      badge.textContent = String(safeCount);
      badge.hidden = safeCount <= 0;
    });

    document.querySelectorAll('[data-cart-open]').forEach(trigger => {
      let badge = trigger.querySelector('[data-cart-count]');

      if (!badge && safeCount > 0) {
        badge = document.createElement('span');
        badge.className = 'cart-count';
        badge.dataset.cartCount = '';
        trigger.appendChild(badge);
      }

      if (badge) {
        badge.textContent = String(safeCount);
        badge.hidden = safeCount <= 0;
      }
    });

    const drawerCount = drawer.querySelector('[data-cart-drawer-count]');
    if (drawerCount) {
      drawerCount.textContent = String(safeCount);
    }
  };

  const emptyCartMarkup = () => `
    <div class="cart-drawer-empty">
      <div class="cart-drawer-empty-icon">🛍️</div>
      <h3>Your cart is empty</h3>
      <p>Choose a product and add it to your cart.</p>
      <a href="products.php" class="cart-drawer-browse" data-cart-close>
        Browse Products
      </a>
    </div>
  `;

  const renderCart = data => {
    const items = Array.isArray(data.items) ? data.items : [];
    updateBadges(data.item_count || 0);

    const subtotal = drawer.querySelector('[data-cart-subtotal]');
    if (subtotal) {
      subtotal.textContent = data.subtotal_formatted || '₹0.00';
    }

    drawerFooter.hidden = items.length === 0;

    if (items.length === 0) {
      body.innerHTML = emptyCartMarkup();
      return;
    }

    body.innerHTML = items.map(item => {
      const options = [
        item.color_name,
        item.design_name
      ].filter(Boolean).map(escapeHtml).join(' · ');

      const tamilName = item.product_name_tamil
        ? `<small lang="ta">${escapeHtml(item.product_name_tamil)}</small>`
        : '';

      const notes = item.notes
        ? `<p class="cart-drawer-notes">${escapeHtml(item.notes)}</p>`
        : '';

      const atMinimum =
        Number(item.quantity) <= Number(item.minimum_order_qty);

      const maximum = item.maximum_quantity === null
        ? ''
        : `data-maximum="${Number(item.maximum_quantity)}"`;

      return `
        <article class="cart-drawer-item" data-cart-item="${Number(item.id)}">
          <a
            class="cart-drawer-image"
            href="product.php?slug=${encodeURIComponent(item.slug)}"
          >
            <img
              src="${escapeHtml(item.image)}"
              alt="${escapeHtml(item.product_name)}"
            >
          </a>

          <div class="cart-drawer-item-copy">
            <div class="cart-drawer-item-heading">
              <a href="product.php?slug=${encodeURIComponent(item.slug)}">
                <strong>${escapeHtml(item.product_name)}</strong>
                ${tamilName}
              </a>

              <button
                type="button"
                class="cart-drawer-remove"
                data-cart-remove="${Number(item.id)}"
                aria-label="Remove ${escapeHtml(item.product_name)}"
              >×</button>
            </div>

            ${options ? `<p class="cart-drawer-options">${options}</p>` : ''}
            ${notes}

            <div class="cart-drawer-item-bottom">
              <div
                class="cart-quantity-control"
                data-minimum="${Number(item.minimum_order_qty)}"
                data-step="${Number(item.quantity_step)}"
                ${maximum}
              >
                <button
                  type="button"
                  data-cart-decrease="${Number(item.id)}"
                  aria-label="Decrease quantity"
                  ${atMinimum ? 'disabled' : ''}
                >−</button>

                <output>${Number(item.quantity)}</output>

                <button
                  type="button"
                  data-cart-increase="${Number(item.id)}"
                  aria-label="Increase quantity"
                >+</button>
              </div>

              <strong class="cart-drawer-line-total">
                ${escapeHtml(item.line_total_formatted)}
              </strong>
            </div>
          </div>
        </article>
      `;
    }).join('');
  };

  const parseResponse = async response => {
    const text = await response.text();

    try {
      return JSON.parse(text);
    } catch {
      throw new Error(
        text.trim() || 'The cart service returned an invalid response.'
      );
    }
  };

  const loadCart = async () => {
    body.innerHTML = `
      <div class="cart-drawer-loading">
        <span class="cart-loader"></span>
        Loading your cart...
      </div>
    `;

    const response = await fetch(summaryUrl, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    });

    const result = await parseResponse(response);

    if (response.status === 401 && result.data?.login_url) {
      window.location.href = result.data.login_url;
      return;
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Unable to load your cart.');
    }

    renderCart(result.data);
  };

  const openCart = async ({refresh = true} = {}) => {
    lastFocusedElement = document.activeElement;
    overlay.hidden = false;
    drawer.classList.add('open');
    overlay.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('cart-drawer-open');

    drawer.querySelector('[data-cart-close]')?.focus();

    if (refresh) {
      try {
        await loadCart();
      } catch (error) {
        body.innerHTML = `
          <div class="cart-drawer-error">
            <h3>Unable to load cart</h3>
            <p>${escapeHtml(error.message)}</p>
            <button type="button" data-cart-retry>Try Again</button>
          </div>
        `;
        drawerFooter.hidden = true;
      }
    }
  };

  const closeCart = () => {
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('cart-drawer-open');

    window.setTimeout(() => {
      overlay.hidden = true;
    }, 260);

    if (lastFocusedElement instanceof HTMLElement) {
      lastFocusedElement.focus();
    }
  };

  const postCartAction = async payload => {
    if (requestInProgress) return;

    requestInProgress = true;
    drawer.classList.add('is-updating');

    try {
      const csrf = document.querySelector(
        'meta[name="csrf-token"]'
      )?.content || '';

      const formData = new FormData();
      formData.set('csrf_token', csrf);

      Object.entries(payload).forEach(([key, value]) => {
        formData.set(key, String(value));
      });

      const response = await fetch(summaryUrl, {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });

      const result = await parseResponse(response);

      if (response.status === 401 && result.data?.login_url) {
        window.location.href = result.data.login_url;
        return;
      }

      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Unable to update your cart.');
      }

      renderCart(result.data);
      showToast(result.message || 'Cart updated.');
    } catch (error) {
      showToast(error.message || 'Unable to update cart.', 'error');
    } finally {
      requestInProgress = false;
      drawer.classList.remove('is-updating');
    }
  };

  document.addEventListener('click', event => {
    const openTrigger = event.target.closest('[data-cart-open]');
    if (openTrigger) {
      event.preventDefault();
      openCart();
      return;
    }

    if (
      event.target.closest('[data-cart-close]')
      || event.target === overlay
    ) {
      closeCart();
      return;
    }

    if (event.target.closest('[data-cart-retry]')) {
      loadCart().catch(error => {
        showToast(error.message, 'error');
      });
      return;
    }

    const removeButton = event.target.closest('[data-cart-remove]');
    if (removeButton) {
      postCartAction({
        action: 'remove',
        item_id: removeButton.dataset.cartRemove
      });
      return;
    }

    const decreaseButton = event.target.closest('[data-cart-decrease]');
    if (decreaseButton) {
      const item = decreaseButton.closest('[data-cart-item]');
      const control = decreaseButton.closest('.cart-quantity-control');
      const current = Number(control?.querySelector('output')?.textContent);
      const minimum = Number(control?.dataset.minimum || 1);
      const step = Number(control?.dataset.step || 1);
      const next = Math.max(minimum, current - step);

      postCartAction({
        action: 'quantity',
        item_id: item?.dataset.cartItem || 0,
        quantity: next
      });
      return;
    }

    const increaseButton = event.target.closest('[data-cart-increase]');
    if (increaseButton) {
      const item = increaseButton.closest('[data-cart-item]');
      const control = increaseButton.closest('.cart-quantity-control');
      const current = Number(control?.querySelector('output')?.textContent);
      const step = Number(control?.dataset.step || 1);
      const maximum = Number(control?.dataset.maximum || 0);
      const proposed = current + step;
      const next = maximum > 0 ? Math.min(maximum, proposed) : proposed;

      if (maximum > 0 && proposed > maximum) {
        showToast('Maximum available stock reached.', 'error');
        return;
      }

      postCartAction({
        action: 'quantity',
        item_id: item?.dataset.cartItem || 0,
        quantity: next
      });
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && drawer.classList.contains('open')) {
      closeCart();
    }
  });

  document.addEventListener('submit', async event => {
    const form = event.target.closest('.js-add-to-cart-form');

    if (!form) {
      return;
    }

    event.preventDefault();

    const button = form.querySelector('[data-add-button]');
    const originalText = button?.textContent || 'Add to Cart';

    if (button) {
      button.disabled = true;
      button.textContent = 'Adding...';
    }

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });

      const result = await parseResponse(response);

      if (response.status === 401 && result.data?.login_url) {
        window.location.href = result.data.login_url;
        return;
      }

      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Unable to add this product.');
      }

      renderCart(result.data.cart);
      showToast(result.message || 'Product added to cart.');
      await openCart({refresh: false});
    } catch (error) {
      showToast(error.message || 'Unable to add product.', 'error');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = originalText;
      }
    }
  });
})();
