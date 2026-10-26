/* =========================================================
   Khamba Admin – app.js
   One file for every page. Each block below only runs when
   the elements it needs are actually present on the current
   page, so loading this same file on Dashboard / Orders /
   Products never throws errors or does unnecessary work.
   ========================================================= */
$(function() {

  lucide.createIcons();

  /* =========================================================
     1. SHARED ON EVERY PAGE
     Sidebar collapse/drawer, theme, customizer, search, toast.
     ========================================================= */

  window.showToast = function(msg, icon) {
    $('#toastIcon').attr('data-lucide', icon || 'check-circle');
    $('#toastMsg').text(msg);
    lucide.createIcons();
    bootstrap.Toast.getOrCreateInstance(document.getElementById('toastHost'), {
      delay: 2600
    }).show();
  };

  /* Sidebar collapse (desktop) + drawer (mobile) */
  if (localStorage.getItem('khamba-collapsed') === '1') $('body').addClass('collapsed');
  $(document).on('click', '#sbToggle', function() {
    $('body').toggleClass('collapsed');
    localStorage.setItem('khamba-collapsed', $('body').hasClass('collapsed') ? 1 : 0);
  });
  $('#mobileToggle').on('click', () => $('body').addClass('drawer'));
  $('#backdrop').on('click', () => $('body').removeClass('drawer'));
  $(document).on('click', '.sb-link', () => $('body').removeClass('drawer'));

  // The "Dashboards" parent link normally just expands/collapses its
  // submenu (see .sb-parent in the HTML). But when the sidebar is
  // collapsed to icons-only, that submenu is hidden by CSS and there is
  // nowhere to show it — so in that one case, clicking the icon instead
  // takes the user straight to Dashboard 1.
  $(document).on('click', '.sb-parent', function (e) {
    if ($('body').hasClass('collapsed') && innerWidth >= 992) {
      e.preventDefault();
      window.location.href = 'index.html';
    }
  });

  const tips = [...document.querySelectorAll('.sb-link')].map(el => new bootstrap.Tooltip(el, {
    trigger: 'hover',
    placement: 'right'
  }));

  function syncTips() {
    tips.forEach(t => ($('body').hasClass('collapsed') && innerWidth >= 992) ? t.enable() : t.disable());
  }
  syncTips();
  $(document).on('click', '#sbToggle', syncTips);
  $(window).on('resize', syncTips);

  /* Date range dropdown label (dashboard only, but harmless elsewhere) */
  $('#rangeMenu').on('click', 'a', function(e) {
    e.preventDefault();
    $('#rangeLabel').text($(this).text());
  });

  /* Search: clicking a scope fills the input */
  const $s = $('.search'),
    $in = $('#search');
  $in.on('focus click', () => {
    if (!$in.val()) $s.addClass('open');
  });
  $in.on('input', () => $s.toggleClass('open', !$in.val()));
  $in.on('keydown', e => {
    if (e.key === 'Escape') {
      $s.removeClass('open');
      $in.blur();
    }
  });
  $(document).on('mousedown', e => {
    if (!$(e.target).closest('.search').length) $s.removeClass('open');
  });
  $('#searchPanel').on('mousedown', 'a', function(e) {
    e.preventDefault();
    $in.val($(this).data('scope') + ': ').trigger('focus');
    $s.removeClass('open');
  });
  $(document).on('keydown', e => {
    if (e.key === '/' && !$(e.target).is('input,textarea')) {
      e.preventDefault();
      $in.trigger('focus');
    }
  });

  /* Theme toggle */
  function setTheme(t) {
    $('html').attr('data-bs-theme', t);
    localStorage.setItem('khamba-theme', t);
    $('#themeToggle').html(`<i data-lucide="${t === 'dark' ? 'sun' : 'moon'}"></i>`);
    lucide.createIcons();
    $(document).trigger('khamba:theme', t);
  }
  $('#themeToggle').on('click', () => setTheme($('html').attr('data-bs-theme') === 'dark' ? 'light' : 'dark'));
  if (localStorage.getItem('khamba-theme') === 'dark') setTheme('dark');

  /* Customizer: sidebar color + font family */
  const SB_COLORS = ['#0f1115', '#0f172a', '#1e1b4b', '#052e2b', '#2a1233', '#3b1d0f'];
  const FONTS = ['Plus Jakarta Sans', 'Inter', 'Poppins', 'DM Sans', 'Manrope'];
  const root = document.documentElement.style;

  function setSb(col) {
    root.setProperty('--sb', col);
    localStorage.setItem('khamba-sb', col);
    $('#swColors button').removeClass('on').filter((_, b) => $(b).data('c') === col).addClass('on');
  }

  function setFont(f) {
    const stack = `'${f}',system-ui,sans-serif`;
    root.setProperty('--font', stack);
    root.setProperty('--bs-body-font-family', stack);
    if (window.Chart) Chart.defaults.font.family = stack;
    localStorage.setItem('khamba-font', f);
    $('#swFonts button').removeClass('on').filter((_, b) => $(b).data('f') === f).addClass('on');
    $(document).trigger('khamba:font', f);
  }
  $('#swColors').html(SB_COLORS.map(c => `<button data-c="${c}" style="background:${c}" title="${c}"></button>`).join('') +
    '<input type="color" id="swCustom" value="#0f1115" title="Custom color">');
  $('#swFonts').html(FONTS.map(f => `<button data-f="${f}" style="font-family:'${f}'">${f}</button>`).join(''));
  $('#swColors').on('click', 'button', function() {
    setSb($(this).data('c'));
  });
  $('#swCustom').on('input', function() {
    setSb(this.value);
  });
  $('#swFonts').on('click', 'button', function() {
    setFont($(this).data('f'));
  });
  $('#swFab').on('click', () => $('#swPanel').toggleClass('open'));
  $('#swClose').on('click', () => $('#swPanel').removeClass('open'));
  $(document).on('mousedown', e => {
    if (!$(e.target).closest('#swPanel,#swFab').length) $('#swPanel').removeClass('open');
  });
  $('#swReset').on('click', () => {
    localStorage.removeItem('khamba-sb');
    localStorage.removeItem('khamba-font');
    setSb(SB_COLORS[0]);
    setFont(FONTS[0]);
  });
  setSb(localStorage.getItem('khamba-sb') || SB_COLORS[0]);
  setFont(localStorage.getItem('khamba-font') || FONTS[0]);
  lucide.createIcons();

  /* =========================================================
     2. DASHBOARD ONLY (index.html) — runs only if #salesChart
     exists, i.e. only on the dashboard page.
     ========================================================= */
  if (document.getElementById('salesChart')) {

    const RANGES = {
      '7 days': {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        rev: [28400, 31200, 26800, 35600, 38900, 42100, 42450],
        ord: [142, 160, 138, 181, 197, 214, 252]
      },
      '30 days': {
        labels: ['W1', 'W2', 'W3', 'W4'],
        rev: [52300, 61800, 68400, 62950],
        ord: [268, 318, 352, 346]
      },
      '3 months': {
        labels: ['Jul', 'Aug', 'Sep'],
        rev: [201400, 228900, 245450],
        ord: [1042, 1176, 1284]
      },
      '12 months': {
        labels: ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
        rev: [142000, 188000, 231000, 152000, 148000, 164000, 177000, 186000, 195000, 201400, 228900, 245450],
        ord: [730, 960, 1190, 780, 760, 840, 910, 960, 1010, 1042, 1176, 1284]
      }
    };
    const GROWTH = {
      labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
      data: [420, 470, 510, 560, 610, 684]
    };
    let sales, growth;

    function chartColors() {
      const dark = $('html').attr('data-bs-theme') === 'dark';
      return {
        grid: dark ? '#242832' : '#eef0f3',
        tick: dark ? '#9aa1ae' : '#6b7280',
        bar: dark ? '#2b3350' : '#c7d2fe',
        tip: dark ? '#f3f4f6' : '#111827',
        tipText: dark ? '#111' : '#fff'
      };
    }

    function drawSales(key) {
      const d = RANGES[key],
        c = chartColors();
      if (sales) sales.destroy();
      const ctx = document.getElementById('salesChart').getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, 0, 300);
      grad.addColorStop(0, 'rgba(79,70,229,.18)');
      grad.addColorStop(1, 'rgba(79,70,229,0)');
      sales = new Chart(ctx, {
        data: {
          labels: d.labels,
          datasets: [{
            type: 'line',
            label: 'Revenue',
            data: d.rev,
            yAxisID: 'y',
            borderColor: '#4f46e5',
            backgroundColor: grad,
            fill: true,
            tension: .4,
            borderWidth: 2.5,
            pointRadius: 0,
            pointHoverRadius: 5,
            order: 1
          }, {
            type: 'bar',
            label: 'Orders',
            data: d.ord,
            yAxisID: 'y1',
            backgroundColor: c.bar,
            borderRadius: 6,
            maxBarThickness: 28,
            order: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: c.tip,
              titleColor: c.tipText,
              bodyColor: c.tipText,
              padding: 12,
              cornerRadius: 10,
              boxPadding: 4,
              callbacks: {
                label: t => ' ' + t.dataset.label + ': ' + (t.dataset.label === 'Revenue' ? '$' : '') + t.parsed.y.toLocaleString()
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: c.tick
              },
              border: {
                display: false
              }
            },
            y: {
              grid: {
                color: c.grid
              },
              border: {
                display: false
              },
              ticks: {
                color: c.tick,
                callback: v => '$' + v / 1000 + 'k'
              }
            },
            y1: {
              position: 'right',
              grid: {
                display: false
              },
              border: {
                display: false
              },
              ticks: {
                color: c.tick
              }
            }
          }
        }
      });
    }

    function drawGrowth() {
      if (growth) growth.destroy();
      growth = new Chart(document.getElementById('growthChart'), {
        type: 'line',
        data: {
          labels: GROWTH.labels,
          datasets: [{
            data: GROWTH.data,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,.12)',
            fill: true,
            tension: .4,
            borderWidth: 2,
            pointRadius: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            x: {
              display: false
            },
            y: {
              display: false
            }
          }
        }
      });
    }

    drawSales('7 days');
    drawGrowth();

    $('#chartRange').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      drawSales($(this).data('r'));
    });
    $(document).on('khamba:theme khamba:font', () => {
      drawSales($('#chartRange .on').data('r') || '7 days');
      drawGrowth();
    });

    $('#downloadReport').on('click', function() {
      const csv = 'Metric,Value,Change\nTotal revenue,$245450,+14.9%\nTotal orders,1284,+8.2%\nNew customers,684,+12.4%\nAvg order value,$192.40,+5.7%';
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], {
        type: 'text/csv'
      }));
      a.download = 'store-report.csv';
      a.click();
      window.showToast('Report downloaded as store-report.csv');
    });
  }

  /* =========================================================
     3. ORDERS PAGE ONLY (orders.html) — runs only if
     #ordersTable exists.
     ========================================================= */
  if (document.getElementById('ordersTable')) {

    const $rows = () => $('#ordersTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      pay: '',
      sort: 'new'
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const matchesTab = state.tab === 'all' || $r.data('status') === state.tab;
        const matchesPay = !state.pay || $r.find('.badge-x').first().text().trim() === state.pay;
        const matchesQ = !state.q || $r.text().toLowerCase().includes(state.q);
        const show = matchesTab && matchesPay && matchesQ;
        $r.toggle(show);
        if (show) visible++;
      });
      $('#pgInfo').text(visible ? `Showing ${visible} of ${$rows().length} orders` : 'No orders match your filters');

      const sorted = $rows().filter(':visible').get().sort((a, b) => {
        const $a = $(a),
          $b = $(b);
        if (state.sort === 'high') return $b.data('total') - $a.data('total');
        if (state.sort === 'low') return $a.data('total') - $b.data('total');
        if (state.sort === 'old') return new Date($a.data('date')) - new Date($b.data('date'));
        return new Date($b.data('date')) - new Date($a.data('date'));
      });
      $.each(sorted, (_, row) => $('#ordersTable tbody').append(row));
    }

    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#orderSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#payFilter').on('change', function() {
      state.pay = this.value;
      apply();
    });
    $('#sortFilter').on('change', function() {
      state.sort = this.value;
      apply();
    });

    function syncBulk() {
      const n = $('.rowChk:checked').length;
      $('#bulkBar').toggleClass('on', n > 0);
      $('#bulkCount').text(n);
      $('#selectAll').prop('checked', n > 0 && n === $rows().filter(':visible').find('.rowChk').length);
    }
    $('#ordersTable').on('change', '.rowChk', syncBulk);
    $('#selectAll').on('change', function() {
      $rows().filter(':visible').find('.rowChk').prop('checked', this.checked);
      syncBulk();
    });

    function markShipped(id) {
      const $r = $('tr[data-status]').filter((_, r) => $(r).find('.oid').attr('href') === '#order-' + id);
      $r.attr('data-status', 'Shipped').find('td:eq(6) .badge-x').attr('class', 'badge-x s-Shipped').text('Shipped');
    }

    function markCancelled(id) {
      const $r = $('tr[data-status]').filter((_, r) => $(r).find('.oid').attr('href') === '#order-' + id);
      $r.attr('data-status', 'Cancelled').find('td:eq(6) .badge-x').attr('class', 'badge-x s-Cancelled').text('Cancelled');
      $r.attr('data-payment', 'Refunded').find('td:eq(5) .badge-x').attr('class', 'badge-x s-Refunded').text('Refunded');
    }
    $(document).on('click', '[data-mark-ship]', function() {
      markShipped($(this).data('mark-ship'));
      window.showToast('Order marked as shipped');
    });
    $(document).on('click', '[data-mark-cancel]', function() {
      markCancelled($(this).data('mark-cancel'));
      window.showToast('Order cancelled', 'circle-x');
    });

    $('#bulkBar').on('click', '[data-bulk]', function() {
      const action = $(this).data('bulk');
      const ids = $('.rowChk:checked').closest('tr').map(function() {
        return $(this).find('.oid').attr('href').replace('#order-', '');
      }).get();
      ids.forEach(id => {
        if (action === 'ship') markShipped(id);
        if (action === 'cancel') markCancelled(id);
      });
      $('.rowChk').prop('checked', false);
      syncBulk();
      if (action === 'ship') window.showToast(`${ids.length} order(s) marked as shipped`);
      if (action === 'cancel') window.showToast(`${ids.length} order(s) cancelled`, 'circle-x');
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Order,Customer,Total,Payment,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.oid').text(), $r.find('.cust b').text(), $r.data('total'), $r.data('payment'), $r.data('status')].join(','));
      });
      const blob = new Blob([lines.join('\n')], {
        type: 'text/csv'
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'orders.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} orders to orders.csv`);
    });

    $('#newOrderForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newOrderModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Order created — it will appear in the list once saved to the store.');
    });

    apply();
  }

  /* =========================================================
     4. PRODUCTS PAGE ONLY (products.html) — runs only if
     #productsTable exists.
     ========================================================= */
  if (document.getElementById('productsTable')) {

    const $rows = () => $('#productsTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      cat: '',
      sort: 'new'
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const matchesTab = state.tab === 'all' || $r.data('status') === state.tab;
        const matchesCat = !state.cat || $r.data('cat') === state.cat;
        const matchesQ = !state.q || $r.text().toLowerCase().includes(state.q);
        const show = matchesTab && matchesCat && matchesQ;
        $r.toggle(show);
        if (show) visible++;
      });
      $('#pgInfo').text(visible ? `Showing ${visible} of ${$rows().length} products` : 'No products match your filters');

      const sorted = $rows().filter(':visible').get().sort((a, b) => {
        const $a = $(a),
          $b = $(b);
        if (state.sort === 'high') return $b.data('price') - $a.data('price');
        if (state.sort === 'low') return $a.data('price') - $b.data('price');
        if (state.sort === 'stock') return $a.data('stock') - $b.data('stock');
        return 0;
      });
      $.each(sorted, (_, row) => $('#productsTable tbody').append(row));
    }

    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#productSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#catFilter').on('change', function() {
      state.cat = this.value;
      apply();
    });
    $('#sortFilter').on('change', function() {
      state.sort = this.value;
      apply();
    });

    function syncBulk() {
      const n = $('.rowChk:checked').length;
      $('#bulkBar').toggleClass('on', n > 0);
      $('#bulkCount').text(n);
      $('#selectAll').prop('checked', n > 0 && n === $rows().filter(':visible').find('.rowChk').length);
    }
    $('#productsTable').on('change', '.rowChk', syncBulk);
    $('#selectAll').on('change', function() {
      $rows().filter(':visible').find('.rowChk').prop('checked', this.checked);
      syncBulk();
    });

    $('#bulkBar').on('click', '[data-bulk]', function() {
      const action = $(this).data('bulk');
      const $checked = $('.rowChk:checked').closest('tr');
      if (action === 'activate') $checked.attr('data-status', 'Active').find('td:eq(5) .badge-x').attr('class', 'badge-x s-Delivered').text('Active');
      if (action === 'delete') $checked.fadeOut(150, function() {
        $(this).remove();
      });
      const n = $checked.length;
      $('.rowChk').prop('checked', false);
      syncBulk();
      if (action === 'activate') window.showToast(`${n} product(s) set to active`);
      if (action === 'delete') window.showToast(`${n} product(s) deleted`, 'trash-2');
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Product,Category,Price,Stock,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.oid b').text(), $r.data('cat'), $r.data('price'), $r.data('stock'), $r.data('status')].join(','));
      });
      const blob = new Blob([lines.join('\n')], {
        type: 'text/csv'
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'products.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} products to products.csv`);
    });

    $('#newProductForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newProductModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Product added — it will appear in the catalog once saved.');
    });

    apply();
  }

  /* =========================================================
     5. ANALYTICS PAGE ONLY (analytics.html) — runs only if
     #analyticsChart exists.
     ========================================================= */
  if (document.getElementById('analyticsChart')) {

    const RANGES = {
      '7 days': {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        rev: [28400, 31200, 26800, 35600, 38900, 42100, 42450],
        ord: [142, 160, 138, 181, 197, 214, 252]
      },
      '30 days': {
        labels: ['W1', 'W2', 'W3', 'W4'],
        rev: [52300, 61800, 68400, 62950],
        ord: [268, 318, 352, 346]
      },
      '3 months': {
        labels: ['Jul', 'Aug', 'Sep'],
        rev: [201400, 228900, 245450],
        ord: [1042, 1176, 1284]
      },
      '12 months': {
        labels: ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
        rev: [142000, 188000, 231000, 152000, 148000, 164000, 177000, 186000, 195000, 201400, 228900, 245450],
        ord: [730, 960, 1190, 780, 760, 840, 910, 960, 1010, 1042, 1176, 1284]
      }
    };
    const CATS = {
      labels: ['Electronics', 'Accessories', 'Footwear', 'Home', 'Fitness'],
      data: [34, 22, 18, 16, 10],
      colors: ['#4f46e5', '#818cf8', '#f59e0b', '#10b981', '#ec4899']
    };
    let rev, cat;

    function chartColors() {
      const dark = $('html').attr('data-bs-theme') === 'dark';
      return {
        grid: dark ? '#242832' : '#eef0f3',
        tick: dark ? '#9aa1ae' : '#6b7280',
        bar: dark ? '#2b3350' : '#c7d2fe',
        tip: dark ? '#f3f4f6' : '#111827',
        tipText: dark ? '#111' : '#fff',
        ring: dark ? '#14171d' : '#fff'
      };
    }

    function drawRev(key) {
      const d = RANGES[key],
        c = chartColors();
      if (rev) rev.destroy();
      const ctx = document.getElementById('analyticsChart').getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, 0, 300);
      grad.addColorStop(0, 'rgba(79,70,229,.18)');
      grad.addColorStop(1, 'rgba(79,70,229,0)');
      rev = new Chart(ctx, {
        data: {
          labels: d.labels,
          datasets: [{
            type: 'line',
            label: 'Revenue',
            data: d.rev,
            yAxisID: 'y',
            borderColor: '#4f46e5',
            backgroundColor: grad,
            fill: true,
            tension: .4,
            borderWidth: 2.5,
            pointRadius: 0,
            pointHoverRadius: 5,
            order: 1
          }, {
            type: 'bar',
            label: 'Orders',
            data: d.ord,
            yAxisID: 'y1',
            backgroundColor: c.bar,
            borderRadius: 6,
            maxBarThickness: 28,
            order: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: c.tip,
              titleColor: c.tipText,
              bodyColor: c.tipText,
              padding: 12,
              cornerRadius: 10,
              boxPadding: 4,
              callbacks: {
                label: t => ' ' + t.dataset.label + ': ' + (t.dataset.label === 'Revenue' ? '$' : '') + t.parsed.y.toLocaleString()
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: c.tick
              },
              border: {
                display: false
              }
            },
            y: {
              grid: {
                color: c.grid
              },
              border: {
                display: false
              },
              ticks: {
                color: c.tick,
                callback: v => '$' + v / 1000 + 'k'
              }
            },
            y1: {
              position: 'right',
              grid: {
                display: false
              },
              border: {
                display: false
              },
              ticks: {
                color: c.tick
              }
            }
          }
        }
      });
    }

    function drawCat() {
      const c = chartColors();
      if (cat) cat.destroy();
      cat = new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
          labels: CATS.labels,
          datasets: [{
            data: CATS.data,
            backgroundColor: CATS.colors,
            borderColor: c.ring,
            borderWidth: 3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '68%',
          plugins: {
            legend: {
              display: false
            }
          }
        }
      });
    }

    drawRev('7 days');
    drawCat();

    $('#chartRange').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      drawRev($(this).data('r'));
    });
    $(document).on('khamba:theme khamba:font', () => {
      drawRev($('#chartRange .on').data('r') || '7 days');
      drawCat();
    });

    $('#exportBtn').on('click', function() {
      const csv = 'Metric,Value,Change\nTotal revenue,$245450,+14.9%\nTotal sessions,42860,+9.3%\nConversion rate,3.0%,+0.4%\nAvg session value,$28.60,+6.1%';
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], {
        type: 'text/csv'
      }));
      a.download = 'analytics-report.csv';
      a.click();
      window.showToast('Report downloaded as analytics-report.csv');
    });
  }

  /* =========================================================
     6. CATEGORIES PAGE ONLY (categories.html) — runs only if
     #catGrid exists.
     ========================================================= */
  if (document.getElementById('catGrid')) {

    function apply() {
      const q = $('#catSearch').val().trim().toLowerCase();
      const status = $('#statusFilter').val();
      let visible = 0;
      $('#catGrid > div').each(function() {
        const $c = $(this);
        const show = (!status || $c.data('status') === status) && (!q || $c.data('name').includes(q));
        $c.toggle(show);
        if (show) visible++;
      });
      $('#catInfo').text(`Showing ${visible} of ${$('#catGrid > div').length} categories`);
    }
    $('#catSearch').on('input', apply);
    $('#statusFilter').on('change', apply);

    $('#exportBtn').on('click', function() {
      const lines = ['Category,Products,Status'];
      $('#catGrid > div:visible').each(function() {
        const $c = $(this);
        lines.push([$c.find('.cat-name').text(), $c.find('.cat-count').text().replace(' products', ''), $c.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'categories.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} categories to categories.csv`);
    });

    $('#newCategoryForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newCategoryModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Category created — it will appear in the list once saved.');
    });

    apply();
  }

  /* =========================================================
     7. INVENTORY PAGE ONLY (inventory.html) — runs only if
     #invTable exists.
     ========================================================= */
  if (document.getElementById('invTable')) {

    const $rows = () => $('#invTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      cat: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.cat || $r.data('cat') === state.cat) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#invInfo').text(`Showing ${visible} of ${$rows().length} items`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#invSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#catFilter').on('change', function() {
      state.cat = this.value;
      apply();
    });

    function syncBulk() {
      const n = $('.rowChk:checked').length;
      $('#bulkBar').toggleClass('on', n > 0);
      $('#bulkCount').text(n);
      $('#selectAll').prop('checked', n > 0 && n === $rows().filter(':visible').find('.rowChk').length);
    }
    $('#invTable').on('change', '.rowChk', syncBulk);
    $('#selectAll').on('change', function() {
      $rows().filter(':visible').find('.rowChk').prop('checked', this.checked);
      syncBulk();
    });
    $('#bulkBar').on('click', '[data-bulk]', function() {
      const n = $('.rowChk:checked').length;
      if ($(this).data('bulk') === 'restock') $('.rowChk:checked').closest('tr').attr('data-status', 'In stock').find('td:eq(6) .badge-x').attr('class', 'badge-x s-Delivered').text('In stock');
      $('.rowChk').prop('checked', false);
      syncBulk();
      if (n) window.showToast(`${n} item(s) marked as restocked`);
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Product,Category,In stock,Available,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('b').first().text(), $r.data('cat'), $r.find('td:eq(3)').text(), $r.find('td:eq(5)').text(), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'inventory.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} items to inventory.csv`);
    });

    $('#adjustForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('adjustModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Stock adjustment saved.');
    });

    apply();
  }

  /* =========================================================
     8. CUSTOMERS PAGE ONLY (customers.html) — runs only if
     #custTable exists.
     ========================================================= */
  if (document.getElementById('custTable')) {

    const $rows = () => $('#custTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      sort: 'new'
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#custInfo').text(`Showing ${visible} of ${$rows().length} customers`);

      const sorted = $rows().filter(':visible').get().sort((a, b) => {
        const $a = $(a),
          $b = $(b);
        if (state.sort === 'spend') return $b.data('spend') - $a.data('spend');
        if (state.sort === 'orders') return $b.data('orders') - $a.data('orders');
        return 0;
      });
      $.each(sorted, (_, row) => $('#custTable tbody').append(row));
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#custSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#sortFilter').on('change', function() {
      state.sort = this.value;
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Customer,Location,Orders,Total spent,Segment'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.cust b').text(), $r.find('td:eq(1)').text(), $r.data('orders'), $r.data('spend'), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'customers.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} customers to customers.csv`);
    });

    $('#newCustomerForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newCustomerModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Customer added — they will appear in the list once saved.');
    });

    apply();
  }

  /* =========================================================
     9. REVIEWS PAGE ONLY (reviews.html) — runs only if
     #rvList exists.
     ========================================================= */
  if (document.getElementById('rvList')) {

    const $cards = () => $('#rvList .rv-card');
    const state = {
      tab: 'all',
      q: '',
      rating: ''
    };

    function apply() {
      let visible = 0;
      $cards().each(function() {
        const $c = $(this);
        const show = (state.tab === 'all' || $c.data('status') === state.tab) && (!state.rating || String($c.data('rating')) === state.rating) && (!state.q || $c.text().toLowerCase().includes(state.q));
        $c.toggle(show);
        if (show) visible++;
      });
      $('#rvInfo').text(`Showing ${visible} of ${$cards().length} reviews`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#rvSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#ratingFilter').on('change', function() {
      state.rating = this.value;
      apply();
    });

    $('#rvList').on('click', '[data-approve]', function() {
      const $c = $(this).closest('.rv-card');
      $c.attr('data-status', 'Published').find('.badge-x').attr('class', 'badge-x s-Delivered').text('Published');
      window.showToast('Review published');
    });
    $('#rvList').on('click', '[data-reject]', function() {
      $(this).closest('.rv-card').fadeOut(150, function() {
        $(this).remove();
      });
      window.showToast('Review removed', 'trash-2');
    });
    $('#rvList').on('click', '[data-reply]', function() {
      window.showToast('Reply box would open here', 'reply');
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Customer,Product,Rating,Status'];
      $cards().filter(':visible').each(function() {
        const $c = $(this);
        lines.push([$c.find('.cust b').text(), $c.find('.rv-product').text().trim(), $c.data('rating'), $c.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'reviews.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} reviews to reviews.csv`);
    });

    apply();
  }

  /* =========================================================
     10. DISCOUNTS PAGE ONLY (discounts.html) — runs only if
     #dcTable exists.
     ========================================================= */
  if (document.getElementById('dcTable')) {

    const $rows = () => $('#dcTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      type: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.type || $r.data('type') === state.type) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#dcInfo').text(`Showing ${visible} of ${$rows().length} discounts`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#dcSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#typeFilter').on('change', function() {
      state.type = this.value;
      apply();
    });

    $('#dcTable').on('click', '.icon-btn.sm', function() {
      const code = $(this).closest('tr').find('.dc-code').text();
      navigator.clipboard?.writeText(code);
      window.showToast(`Copied ${code} to clipboard`, 'copy');
    });

    function syncBulk() {
      const n = $('.rowChk:checked').length;
      $('#bulkBar').toggleClass('on', n > 0);
      $('#bulkCount').text(n);
      $('#selectAll').prop('checked', n > 0 && n === $rows().filter(':visible').find('.rowChk').length);
    }
    $('#dcTable').on('change', '.rowChk', syncBulk);
    $('#selectAll').on('change', function() {
      $rows().filter(':visible').find('.rowChk').prop('checked', this.checked);
      syncBulk();
    });
    $('#bulkBar').on('click', '[data-bulk]', function() {
      const n = $('.rowChk:checked').length;
      if ($(this).data('bulk') === 'deactivate') $('.rowChk:checked').closest('tr').attr('data-status', 'Expired').find('td:eq(6) .badge-x').attr('class', 'badge-x s-Refunded').text('Expired');
      $('.rowChk').prop('checked', false);
      syncBulk();
      if (n) window.showToast(`${n} discount(s) deactivated`);
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Code,Type,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.dc-code').text(), $r.data('type'), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'discounts.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} discounts to discounts.csv`);
    });

    $('#newDiscountForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newDiscountModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Discount created — it will appear in the list once saved.');
    });

    apply();
  }

  /* =========================================================
     11. PAYMENTS PAGE ONLY (payments.html) — runs only if
     #txTable exists.
     ========================================================= */
  if (document.getElementById('txTable')) {

    const $rows = () => $('#txTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      method: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.method || $r.data('method') === state.method) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#txInfo').text(`Showing ${visible} of ${$rows().length} transactions`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#txSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#methodFilter').on('change', function() {
      state.method = this.value;
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Transaction,Customer,Method,Amount,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.oid').text(), $r.find('.cust b').text(), $r.data('method'), $r.data('amount'), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'payments.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} transactions to payments.csv`);
    });

    apply();
  }

  /* =========================================================
     12. SHIPMENTS PAGE ONLY (shipments.html)
     ========================================================= */
  if (document.getElementById('shTable')) {
    const $rows = () => $('#shTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      carrier: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.carrier || $r.data('carrier') === state.carrier) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#shInfo').text(`Showing ${visible} of ${$rows().length} shipments`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#shSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#carrierFilter').on('change', function() {
      state.carrier = this.value;
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Tracking,Customer,Carrier,Status,ETA'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.oid').text(), $r.find('.cust b').text(), $r.data('carrier'), $r.data('status'), $r.find('td:eq(5)').text()].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'shipments.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} shipments to shipments.csv`);
    });
    apply();
  }

  /* =========================================================
     13. RETURNS PAGE ONLY (returns.html)
     ========================================================= */
  if (document.getElementById('rtTable')) {
    const $rows = () => $('#rtTable tbody tr');
    const state = {
      tab: 'all',
      q: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#rtInfo').text(`Showing ${visible} of ${$rows().length} returns`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#rtSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });

    $('#rtTable').on('click', '[data-rt-approve]', function() {
      $(this).closest('tr').attr('data-status', 'Approved').find('td:eq(5) .badge-x').attr('class', 'badge-x s-Processing').text('Approved');
      window.showToast('Return approved');
      apply();
    });
    $('#rtTable').on('click', '[data-rt-reject]', function() {
      $(this).closest('tr').attr('data-status', 'Rejected').find('td:eq(5) .badge-x').attr('class', 'badge-x s-Critical').text('Rejected');
      window.showToast('Return rejected', 'circle-x');
      apply();
    });
    $('#rtTable').on('click', '[data-rt-refund]', function() {
      $(this).closest('tr').attr('data-status', 'Refunded').find('td:eq(5) .badge-x').attr('class', 'badge-x s-Delivered').text('Refunded');
      window.showToast('Marked as refunded');
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['RMA,Customer,Product,Amount,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('b').first().text(), $r.find('.cust b').text(), $r.find('td:eq(2)').text(), $r.find('td:eq(4)').text(), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'returns.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} returns to returns.csv`);
    });
    apply();
  }

  /* =========================================================
     14. ABANDONED CARTS PAGE ONLY (abandoned-carts.html)
     ========================================================= */
  if (document.getElementById('acTable')) {
    const $rows = () => $('#acTable tbody tr');
    const state = {
      tab: 'all',
      q: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#acInfo').text(`Showing ${visible} of ${$rows().length} carts`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#acSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });

    $('#acTable').on('click', '[data-remind]', function() {
      $(this).closest('tr').attr('data-status', 'Email sent').find('td:eq(4) .badge-x').attr('class', 'badge-x s-Pending').text('Email sent');
      $(this).replaceWith('<span class="text-secondary small">Reminder sent</span>');
      window.showToast('Recovery email sent', 'mail');
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Customer,Cart value,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.cust b').text(), $r.data('value'), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'abandoned-carts.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} carts to abandoned-carts.csv`);
    });
    apply();
  }

  /* =========================================================
     15. GIFT CARDS PAGE ONLY (gift-cards.html)
     ========================================================= */
  if (document.getElementById('gcTable')) {
    const $rows = () => $('#gcTable tbody tr');
    const state = {
      tab: 'all',
      q: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#gcInfo').text(`Showing ${visible} of ${$rows().length} gift cards`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#gcSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });

    $('#gcTable').on('click', '.icon-btn.sm', function() {
      const code = $(this).closest('tr').find('.dc-code').text();
      navigator.clipboard?.writeText(code);
      window.showToast(`Copied ${code} to clipboard`, 'copy');
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Code,Balance,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.dc-code').text(), $r.find('td:eq(2)').text(), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'gift-cards.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} gift cards to gift-cards.csv`);
    });

    $('#newGiftForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newGiftModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Gift card issued — it will appear in the list once saved.');
    });
    apply();
  }

  /* =========================================================
     16. CAMPAIGNS PAGE ONLY (campaigns.html)
     ========================================================= */
  if (document.getElementById('cpTable')) {
    const $rows = () => $('#cpTable tbody tr');
    const state = {
      tab: 'all',
      q: '',
      channel: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.channel || $r.data('channel') === state.channel) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#cpInfo').text(`Showing ${visible} of ${$rows().length} campaigns`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#cpSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#channelFilter').on('change', function() {
      state.channel = this.value;
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Campaign,Channel,Reach,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('b').first().text(), $r.data('channel'), $r.find('td:eq(2)').text(), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'campaigns.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} campaigns to campaigns.csv`);
    });

    $('#newCampaignForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newCampaignModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Campaign created — it will appear in the list once saved.');
    });
    apply();
  }

  /* =========================================================
     17. COUPONS PAGE ONLY (coupons.html)
     ========================================================= */
  if (document.getElementById('coTable')) {
    const $rows = () => $('#coTable tbody tr');
    const state = {
      tab: 'all',
      q: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#coInfo').text(`Showing ${visible} of ${$rows().length} coupons`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#coSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });

    $('#coTable').on('click', '.icon-btn.sm', function() {
      const code = $(this).closest('tr').find('.dc-code').text();
      navigator.clipboard?.writeText(code);
      window.showToast(`Copied ${code} to clipboard`, 'copy');
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Code,Customer,Value,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.dc-code').text(), $r.find('td:eq(1)').text(), $r.find('td:eq(2)').text(), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'coupons.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} coupons to coupons.csv`);
    });

    $('#newCouponForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newCouponModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Coupon issued — it will appear in the list once saved.');
    });
    apply();
  }

  /* =========================================================
     18. EMAIL MARKETING PAGE ONLY (email.html)
     ========================================================= */
  if (document.getElementById('emTable')) {
    const $rows = () => $('#emTable tbody tr');
    const state = {
      tab: 'all',
      q: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#emInfo').text(`Showing ${visible} of ${$rows().length} emails`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#emSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Email,Audience,Open rate,Click rate,Status'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('b').first().text(), $r.data('audience') || $r.find('td:eq(1)').text(), $r.find('td:eq(3)').text(), $r.find('td:eq(4)').text(), $r.data('status')].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'email-campaigns.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} emails to email-campaigns.csv`);
    });

    $('#newEmailForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newEmailModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Email scheduled — it will appear in the list once saved.');
    });
    apply();
  }

  /* =========================================================
     19. MEDIA LIBRARY PAGE ONLY (media.html)
     ========================================================= */
  if (document.getElementById('mediaGrid')) {
    function apply() {
      const q = $('#mediaSearch').val().trim().toLowerCase();
      const type = $('#typeFilter').val();
      let visible = 0;
      $('#mediaGrid > div').each(function() {
        const $c = $(this);
        const show = (!type || $c.data('type') === type) && (!q || $c.data('name').includes(q));
        $c.toggle(show);
        if (show) visible++;
      });
      $('#mediaInfo').text(`Showing ${visible} of ${$('#mediaGrid > div').length} files`);
    }
    $('#mediaSearch').on('input', apply);
    $('#typeFilter').on('change', apply);

    $('#mediaGrid').on('click', '.media-actions [data-lucide="trash-2"]', function() {
      $(this).closest('.col-6, .col-md-4, .col-xl-3').fadeOut(150, function() {
        $(this).remove();
        apply();
      });
      window.showToast('File deleted', 'trash-2');
    });
    $('#mediaGrid').on('click', '.media-actions [data-lucide="download"]', function() {
      window.showToast('Download started');
    });

    $('#uploadForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Upload started — files will appear once processed.');
    });
    apply();
  }

  /* =========================================================
     20. PAGES PAGE ONLY (pages.html)
     ========================================================= */
  if (document.getElementById('pgTable')) {
    const $rows = () => $('#pgTable tbody tr');
    const state = {
      tab: 'all',
      q: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (state.tab === 'all' || $r.data('status') === state.tab) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#pgInfo').text(`Showing ${visible} of ${$rows().length} pages`);
    }
    $('#tabs').on('click', 'button', function() {
      $(this).addClass('on').siblings().removeClass('on');
      state.tab = $(this).data('t');
      apply();
    });
    $('#pgSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });

    $('#newPageForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newPageModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Page created — it will appear in the list once saved.');
    });
    apply();
  }

  /* =========================================================
     21. MENUS PAGE ONLY (menus.html)
     ========================================================= */
  if (document.getElementById('newMenuForm')) {
    $('.menu-list').on('click', '.icon-btn.sm', function() {
      const removing = $(this).find('[data-lucide="trash-2"]').length > 0;
      if (removing) {
        $(this).closest('.menu-item').fadeOut(150, function() {
          $(this).remove();
        });
        window.showToast('Menu item removed', 'trash-2');
      }
    });
    $('#newMenuForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newMenuModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Menu created — it will appear once saved.');
    });
    $('#newItemForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newItemModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Menu item added.');
    });
  }

  /* =========================================================
     22. TEAM MEMBERS PAGE ONLY (team.html)
     ========================================================= */
  if (document.getElementById('tmTable')) {
    const $rows = () => $('#tmTable tbody tr');
    const state = {
      q: '',
      role: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (!state.role || $r.data('role') === state.role) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#tmInfo').text(`Showing ${visible} of ${$rows().length} members`);
    }
    $('#tmSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#roleFilter').on('change', function() {
      state.role = this.value;
      apply();
    });

    $('#tmTable').on('click', '[data-remove]', function(e) {
      e.preventDefault();
      $(this).closest('tr').fadeOut(150, function() {
        $(this).remove();
        apply();
      });
      window.showToast('Member removed', 'user-minus');
    });

    $('#inviteForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('inviteModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Invite sent.');
    });
    apply();
  }

  /* =========================================================
     23. ROLES & PERMISSIONS PAGE ONLY (roles.html)
     ========================================================= */
  if (document.getElementById('newRoleForm')) {
    $('#newRoleForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('newRoleModal')).hide();
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Role created — it will appear once saved.');
    });
  }

  /* =========================================================
     24. ACTIVITY LOGS PAGE ONLY (logs.html)
     ========================================================= */
  if (document.getElementById('logTable')) {
    const $rows = () => $('#logTable tbody tr');
    const state = {
      q: '',
      cat: ''
    };

    function apply() {
      let visible = 0;
      $rows().each(function() {
        const $r = $(this);
        const show = (!state.cat || $r.data('cat') === state.cat) && (!state.q || $r.text().toLowerCase().includes(state.q));
        $r.toggle(show);
        if (show) visible++;
      });
      $('#logInfo').text(`Showing ${visible} of ${$rows().length} events`);
    }
    $('#logSearch').on('input', function() {
      state.q = this.value.trim().toLowerCase();
      apply();
    });
    $('#catFilter').on('change', function() {
      state.cat = this.value;
      apply();
    });

    $('#exportBtn').on('click', function() {
      const lines = ['Actor,Action,Category,Time,IP'];
      $rows().filter(':visible').each(function() {
        const $r = $(this);
        lines.push([$r.find('.cust b').text(), $r.find('td:eq(1)').text().trim(), $r.data('cat'), $r.find('td:eq(3)').text(), $r.find('td:eq(4)').text()].join(','));
      });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lines.join('\n')], {
        type: 'text/csv'
      }));
      a.download = 'activity-logs.csv';
      a.click();
      window.showToast(`Exported ${lines.length - 1} events to activity-logs.csv`);
    });
    apply();
  }

  /* =========================================================
     25. LOGIN PAGE ONLY (login.html)
     ========================================================= */
  if (document.getElementById('loginForm')) {
    const params = new URLSearchParams(location.search);
    if (params.get('registered') === '1') $('#authSuccess').prop('hidden', false);

    $('#loginForm').on('submit', function(e) {
      e.preventDefault();
      const user = $('#loginUser').val().trim();
      const pass = $('#loginPass').val();
      if (user === 'admin' && pass === 'admin') {
        window.location.href = 'index.html';
      } else {
        $('#authSuccess').prop('hidden', true);
        $('#authError').prop('hidden', false);
      }
    });
  }

  /* =========================================================
     26. REGISTER PAGE ONLY (register.html)
     ========================================================= */
  if (document.getElementById('registerForm')) {
    $('#registerForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      if ($('#regPass').val() !== $('#regPass2').val()) {
        $('#authError').prop('hidden', false);
        return;
      }
      window.location.href = 'login.html?registered=1';
    });
  }

  /* =========================================================
     27. SETTINGS PAGE ONLY (settings.html)
     ========================================================= */
  if (document.getElementById('saveSettings')) {
    $('#saveSettings').on('click', () => window.showToast('Settings saved.'));
  }

  /* =========================================================
     28. INTEGRATIONS PAGE ONLY (integrations.html)
     ========================================================= */
  if (document.querySelector('[data-toggle-int]')) {
    $(document).on('click', '[data-toggle-int]', function() {
      const $card = $(this).closest('.int-card');
      const name = $card.find('b').first().text();
      const connected = $card.data('connected') === true || $card.data('connected') === 'true';
      if (connected) {
        $card.attr('data-connected', 'false');
        $card.find('.badge-x').attr('class', 'badge-x s-Refunded').text('Not connected');
        $(this).removeClass('btn-dark-x').addClass('btn-soft').text('Connect').prepend('<i data-lucide="plug"></i>');
        window.showToast(`${name} disconnected`);
      } else {
        $card.attr('data-connected', 'true');
        $card.find('.badge-x').attr('class', 'badge-x s-Delivered').text('Connected');
        $(this).removeClass('btn-soft').addClass('btn-dark-x').text('Disconnect');
        window.showToast(`${name} connected`, 'plug');
      }
      lucide.createIcons();
    });
  }

  /* =========================================================
     29. NOTIFICATIONS PAGE ONLY (notifications.html)
     ========================================================= */
  if (document.getElementById('saveNotif')) {
    $('#saveNotif').on('click', () => window.showToast('Notification preferences saved.'));
  }

  /* =========================================================
     30. HELP & SUPPORT PAGE ONLY (help.html)
     ========================================================= */
  if (document.getElementById('helpForm')) {
    $('#helpForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }
      this.reset();
      $(this).removeClass('was-validated');
      window.showToast('Message sent — our team will reply within a few hours.');
    });
    $('.help-ch button').on('click', function() {
      window.showToast('Opening ' + $(this).closest('.help-ch').find('b').text().toLowerCase() + '…');
    });
  }

  /* =========================================================
     31. ACCOUNT SETTINGS PAGE ONLY (account-settings.html)
     ========================================================= */
  if (document.getElementById('saveAccount')) {
    $('#saveAccount').on('click', () => window.showToast('Account settings saved.'));
  }


  /* =========================================================
     32. DASHBOARD 2 PAGE ONLY (dashboard-2.html) — runs only if
     #weeklySalesChart exists.
     ========================================================= */
  if (document.getElementById('weeklySalesChart')) {

    // ---- Weekly sales bar chart ----
    const weeklyLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const weeklyValues = [4200, 5100, 3800, 6400, 7200, 8900, 6700];
    let weeklyChart;

    function chartColors() {
      const dark = $('html').attr('data-bs-theme') === 'dark';
      return {
        grid: dark ? '#242832' : '#eef0f3',
        tick: dark ? '#9aa1ae' : '#6b7280',
        bar: '#4f46e5',
        ring: dark ? '#14171d' : '#fff',
        track: dark ? '#242832' : '#eef0f3'
      };
    }

    function drawWeeklySales() {
      const c = chartColors();
      if (weeklyChart) weeklyChart.destroy();
      weeklyChart = new Chart(document.getElementById('weeklySalesChart'), {
        type: 'bar',
        data: {
          labels: weeklyLabels,
          datasets: [{
            data: weeklyValues,
            backgroundColor: c.bar,
            borderRadius: 8,
            maxBarThickness: 36
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { color: c.tick }, border: { display: false } },
            y: { grid: { color: c.grid }, border: { display: false }, ticks: { color: c.tick, callback: v => '$' + v / 1000 + 'k' } }
          }
        }
      });
    }

    // ---- Monthly goal gauge (a 2-segment doughnut with text centred via CSS) ----
    let goalChart;
    const GOAL_PERCENT = 72;

    function drawGoalGauge() {
      const c = chartColors();
      if (goalChart) goalChart.destroy();
      goalChart = new Chart(document.getElementById('goalGaugeChart'), {
        type: 'doughnut',
        data: {
          datasets: [{
            data: [GOAL_PERCENT, 100 - GOAL_PERCENT],
            backgroundColor: [c.bar, c.track],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '78%',
          plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
      });
    }

    drawWeeklySales();
    drawGoalGauge();

    // Redraw both charts when the theme or customizer font changes, same
    // pattern used by the main Dashboard and Analytics pages.
    $(document).on('khamba:theme khamba:font', () => {
      drawWeeklySales();
      drawGoalGauge();
    });

    // ---- Quick tasks checklist: toggling a box updates the "N open" badge ----
    function syncTaskCounter() {
      const open = $('#taskList input[type="checkbox"]:not(:checked)').length;
      $('#taskCounter').text(open + ' open');
    }
    $('#taskList').on('change', 'input[type="checkbox"]', syncTaskCounter);
    syncTaskCounter();
  }


  /* =========================================================
     33. DASHBOARD 3 PAGE ONLY (dashboard-3.html) — runs only if
     #heroSparkline exists.
     ========================================================= */
  if (document.getElementById('heroSparkline')) {

    let sparkChart, catChart;

    function drawHeroSparkline() {
      if (sparkChart) sparkChart.destroy();
      const ctx = document.getElementById('heroSparkline').getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, 0, 90);
      grad.addColorStop(0, 'rgba(255,255,255,.35)');
      grad.addColorStop(1, 'rgba(255,255,255,0)');
      sparkChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
          datasets: [{
            data: [28400, 31200, 26800, 35600, 38900, 42100, 42450],
            borderColor: '#fff',
            backgroundColor: grad,
            fill: true,
            tension: .4,
            borderWidth: 2,
            pointRadius: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { enabled: false } },
          scales: { x: { display: false }, y: { display: false } }
        }
      });
    }

    function drawCategorySplit() {
      const dark = $('html').attr('data-bs-theme') === 'dark';
      if (catChart) catChart.destroy();
      catChart = new Chart(document.getElementById('catSplitChart'), {
        type: 'doughnut',
        data: {
          labels: ['Electronics', 'Accessories', 'Footwear', 'Home'],
          datasets: [{
            data: [34, 22, 18, 16],
            backgroundColor: ['#4f46e5', '#818cf8', '#f59e0b', '#10b981'],
            borderColor: dark ? '#14171d' : '#fff',
            borderWidth: 3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '68%',
          plugins: { legend: { display: false } }
        }
      });
    }

    drawHeroSparkline();
    drawCategorySplit();
    $(document).on('khamba:theme khamba:font', () => { drawHeroSparkline(); drawCategorySplit(); });
  }

});