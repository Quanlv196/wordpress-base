/**
 * NTQ Custom Order – Frontend JavaScript
 *
 * Cart storage  : localStorage (key "nco_cart")
 * API transport : admin-ajax.php via jQuery AJAX
 * No React / Vue – plain jQuery + DOM manipulation.
 *
 * @package NTQ_Custom_Order
 */

/* global jQuery, nco_params */
(function ($) {
  "use strict";

  // -------------------------------------------------------------------------
  // Constants & params injected by wp_localize_script
  // -------------------------------------------------------------------------
  var CART_KEY = "nco_cart";
  var p = window.nco_params || {};
  var i18n = p.i18n || {};
  var AJAX_URL = p.ajax_url || "/wp-admin/admin-ajax.php";
  var NONCE = p.nonce || "";
  var CURRENCY = p.currency || "$";
  var CHECKOUT_URL = p.checkout_url || "";

  // =========================================================================
  // Cart – localStorage helpers
  // =========================================================================
  var Cart = {
    /** @returns {Array} */
    get: function () {
      try {
        return JSON.parse(localStorage.getItem(CART_KEY)) || [];
      } catch (e) {
        return [];
      }
    },

    /** @param {Array} items */
    save: function (items) {
      try {
        localStorage.setItem(CART_KEY, JSON.stringify(items));
      } catch (e) {
        // storage full or private browsing — silently ignore
      }
    },

    /**
     * Add a product to the cart, merging quantity if it already exists.
     *
     * @param {Object} product  – raw API product object
     * @param {number} quantity – units to add (>= 1)
     */
    add: function (product, quantity) {
      var items = this.get();
      var id = parseInt(product.id, 10);
      var idx = -1;

      for (var i = 0; i < items.length; i++) {
        if (items[i].id === id) {
          idx = i;
          break;
        }
      }

      if (idx > -1) {
        items[idx].quantity += quantity;
      } else {
        items.push({
          id: id,
          name: product.title || product.name || "Product",
          price: parseFloat(product.price) || 0,
          image: product.image || product.thumbnail || "",
          quantity: quantity,
        });
      }

      this.save(items);
    },

    /** @param {number} productId */
    remove: function (productId) {
      this.save(
        this.get().filter(function (i) {
          return i.id !== productId;
        }),
      );
    },

    /**
     * @param {number} productId
     * @param {number} qty
     */
    updateQty: function (productId, qty) {
      var items = this.get().map(function (item) {
        return item.id === productId
          ? $.extend({}, item, { quantity: Math.max(1, qty) })
          : item;
      });
      this.save(items);
    },

    /** @returns {number} */
    total: function () {
      return this.get().reduce(function (sum, i) {
        return sum + i.price * i.quantity;
      }, 0);
    },

    clear: function () {
      localStorage.removeItem(CART_KEY);
    },
  };

  // =========================================================================
  // Utility helpers
  // =========================================================================

  /** Format a number as a currency string. */
  function fmt(price) {
    return CURRENCY + parseFloat(price).toFixed(2);
  }

  /** Escape a string for safe insertion as HTML text. */
  function esc(str) {
    return $("<div>")
      .text(String(str || ""))
      .html();
  }

  /**
   * Fire an AJAX POST to admin-ajax.php.
   *
   * @param {string}   action
   * @param {Object}   data
   * @param {Function} onSuccess – receives res.data
   * @param {Function} onError   – receives error message string
   */
  function ajax(action, data, onSuccess, onError) {
    var payload = $.extend({ action: action, nonce: NONCE }, data);

    $.ajax({
      url: AJAX_URL,
      type: "POST",
      data: payload,
      success: function (res) {
        if (res && res.success) {
          onSuccess(res.data);
        } else {
          var msg =
            (res && res.data && res.data.message) ||
            i18n.api_error ||
            "An error occurred.";
          onError(msg);
        }
      },
      error: function (xhr, status, err) {
        onError(i18n.api_error || "Request failed: " + err);
      },
    });
  }

  // =========================================================================
  // Module: Product List  [custom_products]
  // Filter params are sent to the server on every change — no local filtering.
  // =========================================================================

  // -- Module-level state ---------------------------------------------------
  var PER_PAGE = 15;
  var _products = []; // current page's products (from last API call)
  var _totalProducts = 0; // total returned by the API for current filters
  var _activeCategory = ""; // single active category (API supports one at a time)
  var _priceMin = null;
  var _priceMax = null;
  var _currentPage = 1;
  var _fetchInProgress = false;

  function initProductsList() {
    var $wrapper = $("#nco-products-wrapper");
    if (!$wrapper.length) {
      return;
    }

    // 1. Load categories from API into sidebar, then load first page.
    fetchCategories(function () {
      // Bind filter events AFTER categories are rendered.
      bindFilterEvents();
      // Initial load with no filters.
      fetchAndRender();
    });
  }

  // -------------------------------------------------------------------------
  // Fetch category list from API → build sidebar checkboxes
  // -------------------------------------------------------------------------
  function fetchCategories(callback) {
    var $list = $("#nco-category-list");

    ajax(
      "nco_fetch_categories",
      {},
      function (categories) {
        var html = "";

        if (Array.isArray(categories) && categories.length) {
          $.each(categories, function (_, cat) {
            // API can return strings or objects { id, name }.
            var label =
              typeof cat === "object"
                ? cat.name || cat.slug || ""
                : String(cat);
            if (!label) {
              return;
            }
            var safeId = "nco-cat-" + label.replace(/\W+/g, "-");
            html +=
              '<label class="nco-cat-item">' +
              '<input type="checkbox" data-cat="' +
              esc(label) +
              '" id="' +
              esc(safeId) +
              '" />' +
              "<span>" +
              esc(label) +
              "</span>" +
              "</label>";
          });
        }

        $list.html(html || '<span class="nco-filter-loading">\u2014</span>');
        if (typeof callback === "function") {
          callback();
        }
      },
      function () {
        // If categories endpoint fails entirely, still load products.
        $list.html('<span class="nco-filter-loading">\u2014</span>');
        if (typeof callback === "function") {
          callback();
        }
      },
    );
  }

  // -------------------------------------------------------------------------
  // Bind UI events for filter controls
  // -------------------------------------------------------------------------
  function bindFilterEvents() {
    // Category checkboxes – only one active at a time (radio-like behaviour
    // matching most APIs; multi-category requires multiple parallel calls).
    $("#nco-category-list").on("change", "input[type='checkbox']", function () {
      var $checked = $(this);
      // Uncheck all others.
      $("#nco-category-list input[type='checkbox']")
        .not($checked)
        .prop("checked", false);
      _activeCategory = $checked.prop("checked") ? $checked.data("cat") : "";
      _currentPage = 1;
      fetchAndRender();
    });

    // Price range.
    $("#nco-price-apply").on("click", function () {
      var minVal = parseFloat($("#nco-price-min").val());
      var maxVal = parseFloat($("#nco-price-max").val());
      _priceMin = isNaN(minVal) ? null : minVal;
      _priceMax = isNaN(maxVal) ? null : maxVal;
      _currentPage = 1;
      fetchAndRender();
    });

    // Reset all.
    $("#nco-filter-reset").on("click", function () {
      _activeCategory = "";
      _priceMin = null;
      _priceMax = null;
      _currentPage = 1;
      $("#nco-price-min, #nco-price-max").val("");
      $("#nco-category-list input[type='checkbox']").prop("checked", false);
      fetchAndRender();
    });
  }

  // -------------------------------------------------------------------------
  // Call the server (AJAX → PHP → API) then render results
  // -------------------------------------------------------------------------
  function fetchAndRender() {
    if (_fetchInProgress) {
      return;
    }
    _fetchInProgress = true;

    var $loading = $("#nco-products-loading");
    var $grid = $("#nco-products-grid");
    var $toolbar = $("#nco-products-toolbar");
    var $error = $("#nco-products-error");

    $error.addClass("nco-hidden").text("");
    $grid.addClass("nco-hidden");
    $loading.show();

    var params = {};
    if (_activeCategory) {
      params.category = _activeCategory;
    }
    if (_priceMin !== null) {
      params.price_min = _priceMin;
    }
    if (_priceMax !== null) {
      params.price_max = _priceMax;
    }

    ajax(
      "nco_fetch_products",
      params,
      function (products) {
        _fetchInProgress = false;
        $loading.hide();

        _products = Array.isArray(products) ? products : [];
        _totalProducts = _products.length;

        if (_totalProducts === 0) {
          $toolbar.removeClass("nco-hidden");
          $("#nco-products-count").text(
            i18n.no_products || "Không tìm thấy sản phẩm.",
          );
          $grid.html("").addClass("nco-hidden");
          $("#nco-pagination").addClass("nco-hidden").html("");
          return;
        }

        renderProductsPage();
      },
      function (msg) {
        _fetchInProgress = false;
        $loading.hide();
        $error.text(msg).removeClass("nco-hidden");
      },
    );
  }

  // -------------------------------------------------------------------------
  // Render the current page slice from _products
  // -------------------------------------------------------------------------
  function renderProductsPage() {
    var total = _products.length;
    var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    _currentPage = Math.min(Math.max(1, _currentPage), totalPages);
    var start = (_currentPage - 1) * PER_PAGE;
    var pageItems = _products.slice(start, start + PER_PAGE);

    var $wrapper = $("#nco-products-wrapper");
    var $grid = $("#nco-products-grid");
    var $toolbar = $("#nco-products-toolbar");
    var $count = $("#nco-products-count");
    var $pagination = $("#nco-pagination");

    $toolbar.removeClass("nco-hidden");
    $count.html(
      "Hiển thị <strong>" +
        (start + 1) +
        "–" +
        Math.min(start + PER_PAGE, total) +
        "</strong> / <strong>" +
        total +
        "</strong> sản phẩm",
    );

    // Build grid HTML.
    var html = "";
    $.each(pageItems, function (_, p) {
      var pid = parseInt(p.id, 10) || 0;
      var title = esc(p.title || p.name || "Untitled");
      var price = fmt(p.price || 0);
      var img = esc(p.image || p.thumbnail || "");
      var detailHref = "#";
      var detailUrl = $wrapper.data("detail-url") || "";

      if (detailUrl) {
        detailHref = esc(detailUrl.replace(/\/$/, "") + "/" + pid);
      } else if (pid > 0) {
        detailHref = esc(
          window.location.pathname.replace(/\/$/, "") + "/" + pid,
        );
      }

      html +=
        '<div class="nco-product-card">' +
        (img
          ? '<img class="nco-product-card__image" src="' +
            img +
            '" alt="' +
            title +
            '" loading="lazy" />'
          : "") +
        '<div class="nco-product-card__body">' +
        '<h3 class="nco-product-card__title">' +
        title +
        "</h3>" +
        '<p class="nco-product-card__price">' +
        price +
        "</p>" +
        '<div class="nco-product-card__actions">' +
        '<a href="' +
        detailHref +
        '" class="nco-btn nco-btn-primary nco-btn-sm">' +
        esc(i18n.view_detail || "View Detail") +
        "</a>" +
        "</div>" +
        "</div>" +
        "</div>";
    });

    $grid.html(html).removeClass("nco-hidden");

    // Pagination.
    if (totalPages <= 1) {
      $pagination.addClass("nco-hidden").html("");
      return;
    }
    $pagination
      .html(buildPaginationHTML(_currentPage, totalPages))
      .removeClass("nco-hidden");
    $pagination
      .off("click.nco")
      .on(
        "click.nco",
        ".nco-page-btn:not(.is-active):not([disabled])",
        function () {
          var goTo = parseInt($(this).data("page"), 10);
          if (!isNaN(goTo)) {
            _currentPage = goTo;
            renderProductsPage();
            $("html, body").animate(
              { scrollTop: $("#nco-products-wrapper").offset().top - 32 },
              300,
            );
          }
        },
      );
  }

  function buildPaginationHTML(current, total) {
    var html = "";
    html +=
      '<button class="nco-page-btn" data-page="' +
      (current - 1) +
      '"' +
      (current === 1 ? " disabled" : "") +
      ">&laquo;</button>";
    $.each(paginationPages(current, total), function (_, pg) {
      if (pg === "...") {
        html += '<span class="nco-page-ellipsis">&hellip;</span>';
      } else {
        html +=
          '<button class="nco-page-btn' +
          (pg === current ? " is-active" : "") +
          '" data-page="' +
          pg +
          '">' +
          pg +
          "</button>";
      }
    });
    html +=
      '<button class="nco-page-btn" data-page="' +
      (current + 1) +
      '"' +
      (current === total ? " disabled" : "") +
      ">&raquo;</button>";
    return html;
  }

  function paginationPages(current, total) {
    var pages = [];
    if (total <= 7) {
      for (var i = 1; i <= total; i++) {
        pages.push(i);
      }
      return pages;
    }
    pages.push(1);
    if (current > 3) {
      pages.push("...");
    }
    var start = Math.max(2, current - 1);
    var end = Math.min(total - 1, current + 1);
    for (var i = start; i <= end; i++) {
      pages.push(i);
    }
    if (current < total - 2) {
      pages.push("...");
    }
    pages.push(total);
    return pages;
  }

  // =========================================================================
  // Module: Product Detail  [custom_product_detail id="N"]
  // =========================================================================

  function initProductDetail() {
    var $wrapper = $("#nco-product-detail-wrapper");
    if (!$wrapper.length) {
      return;
    }

    var productId = parseInt($wrapper.data("product-id"), 10) || 0;
    var checkoutUrl = $wrapper.data("checkout-url") || CHECKOUT_URL || "";
    var $loading = $("#nco-detail-loading");
    var $inner = $("#nco-product-detail-inner");
    var $error = $("#nco-detail-error");
    var currentProduct = null;

    if (!productId) {
      $loading.hide();
      $error
        .text(i18n.no_product_id || "No product ID specified.")
        .removeClass("nco-hidden");
      return;
    }

    ajax(
      "nco_fetch_product_detail",
      { product_id: productId },
      function (product) {
        currentProduct = product;
        $loading.hide();

        var title = esc(product.title || product.name || "Untitled");
        var price = fmt(product.price || 0);
        var imgSrc = esc(product.image || product.thumbnail || "");
        var desc = esc(product.description || "");
        var addLbl = esc(i18n.add_to_cart || "Add to Cart");

        var imgHtml = imgSrc
          ? '<img class="nco-product-detail__image" src="' +
            imgSrc +
            '" alt="' +
            title +
            '" />'
          : "";

        $inner
          .html(
            '<div class="nco-product-detail">' +
              '<div class="nco-product-detail__image-wrap">' +
              imgHtml +
              "</div>" +
              '<div class="nco-product-detail__info">' +
              '<h1 class="nco-product-detail__title">' +
              title +
              "</h1>" +
              '<p class="nco-product-detail__price">' +
              price +
              "</p>" +
              '<p class="nco-product-detail__desc">' +
              desc +
              "</p>" +
              '<div class="nco-qty-row">' +
              '<span class="nco-qty-label">' +
              esc(i18n.quantity || "Quantity") +
              ":</span>" +
              '<input type="number" id="nco-detail-qty" class="nco-qty-input" value="1" min="1" max="99" />' +
              "</div>" +
              '<button id="nco-add-to-cart-btn" class="nco-btn nco-btn-primary">' +
              addLbl +
              "</button>" +
              "</div>" +
              "</div>",
          )
          .removeClass("nco-hidden");
      },
      function (msg) {
        $loading.hide();
        $error.text(msg).removeClass("nco-hidden");
      },
    );

    // -- Add to cart -------------------------------------------------------
    $wrapper.on("click", "#nco-add-to-cart-btn", function () {
      if (!currentProduct) {
        return;
      }

      var qty = Math.max(1, parseInt($("#nco-detail-qty").val(), 10) || 1);
      Cart.add(currentProduct, qty);

      var $msg = $("#nco-cart-added");
      $msg.removeClass("nco-hidden").hide().fadeIn(200);

      // Auto-hide after 4 s
      setTimeout(function () {
        $msg.fadeOut(400);
      }, 4000);
    });

    // -- Update checkout link href (set from PHP data attribute) -----------
    if (checkoutUrl) {
      $("#nco-go-checkout").attr("href", checkoutUrl);
    }
  }

  // =========================================================================
  // Module: Checkout  [custom_checkout]
  // =========================================================================

  function initCheckout() {
    var $wrapper = $("#nco-checkout-wrapper");
    if (!$wrapper.length) {
      return;
    }

    renderCart();

    // -- Quantity increase / decrease -------------------------------------
    $wrapper.on(
      "click",
      ".nco-cart-qty-plus, .nco-cart-qty-minus",
      function () {
        var $btn = $(this);
        var $row = $btn.closest("[data-pid]");
        var pid = parseInt($row.data("pid"), 10);
        var items = Cart.get();
        var item = null;

        for (var i = 0; i < items.length; i++) {
          if (items[i].id === pid) {
            item = items[i];
            break;
          }
        }

        if (!item) {
          return;
        }

        var delta = $btn.hasClass("nco-cart-qty-plus") ? 1 : -1;
        var newQty = item.quantity + delta;

        if (newQty < 1) {
          Cart.remove(pid);
        } else {
          Cart.updateQty(pid, newQty);
        }

        renderCart();
      },
    );

    // -- Remove item -------------------------------------------------------
    $wrapper.on("click", ".nco-cart-remove", function () {
      var pid = parseInt($(this).closest("[data-pid]").data("pid"), 10);
      Cart.remove(pid);
      renderCart();
    });

    // -- Checkout form submit ----------------------------------------------
    $("#nco-checkout-form").on("submit", function (e) {
      e.preventDefault();

      var $btn = $("#nco-submit-btn");
      var $errBox = $("#nco-checkout-error");
      var name = $.trim($("#nco_name").val());
      var phone = $.trim($("#nco_phone").val());
      var address = $.trim($("#nco_address").val());

      $errBox.addClass("nco-hidden").text("");

      if (!name || !phone || !address) {
        $errBox
          .text(i18n.fill_form || "Please fill in all required fields.")
          .removeClass("nco-hidden");
        return;
      }

      var cartItems = Cart.get();
      if (!cartItems.length) {
        $errBox
          .text(i18n.empty_cart || "Your cart is empty.")
          .removeClass("nco-hidden");
        return;
      }

      // Show loading state on button
      $btn.prop("disabled", true);
      $btn.find(".nco-btn-text").addClass("nco-hidden");
      $btn.find(".nco-btn-spinner").removeClass("nco-hidden");

      ajax(
        "nco_submit_order",
        {
          customer_name: name,
          customer_phone: phone,
          customer_address: address,
          cart_items: JSON.stringify(cartItems),
        },
        function (data) {
          Cart.clear();
          $("#nco-checkout-content").hide();

          var $confirm = $("#nco-order-confirmation");
          if (data && data.order_id) {
            $("#nco-order-id-msg").text("Order ID: #" + data.order_id);
          }
          $confirm.removeClass("nco-hidden").hide().fadeIn(400);
        },
        function (msg) {
          $errBox.text(msg).removeClass("nco-hidden");
          $btn.prop("disabled", false);
          $btn.find(".nco-btn-text").removeClass("nco-hidden");
          $btn.find(".nco-btn-spinner").addClass("nco-hidden");
        },
      );
    });
  }

  /** Re-render the cart table inside [custom_checkout]. */
  function renderCart() {
    var items = Cart.get();
    var $list = $("#nco-cart-items-list");
    var $total = $("#nco-cart-total");
    var $totRow = $("#nco-cart-total-row");
    var $empty = $("#nco-empty-cart");
    var $formCol = $("#nco-form-col");

    if (!items.length) {
      $list.html("");
      $totRow.addClass("nco-hidden");
      $formCol.addClass("nco-hidden");
      $empty.removeClass("nco-hidden");
      return;
    }

    $empty.addClass("nco-hidden");
    $formCol.removeClass("nco-hidden");

    var rows = "";
    $.each(items, function (_, item) {
      var name = esc(item.name || "Product");
      var pid = parseInt(item.id, 10);
      var thumb = item.image
        ? '<img class="nco-cart-thumb" src="' +
          esc(item.image) +
          '" alt="' +
          name +
          '" />'
        : "";

      rows +=
        '<tr data-pid="' +
        pid +
        '">' +
        "<td>" +
        thumb +
        "<span>" +
        name +
        "</span></td>" +
        "<td>" +
        fmt(item.price) +
        "</td>" +
        "<td>" +
        '<div class="nco-qty-ctrl">' +
        '<button type="button" class="nco-cart-qty-minus" title="Decrease">&minus;</button>' +
        '<span class="nco-qty-val">' +
        parseInt(item.quantity, 10) +
        "</span>" +
        '<button type="button" class="nco-cart-qty-plus" title="Increase">&plus;</button>' +
        "</div>" +
        "</td>" +
        "<td>" +
        fmt(item.price * item.quantity) +
        "</td>" +
        "<td>" +
        '<button type="button" class="nco-cart-remove" title="Remove item">&times;</button>' +
        "</td>" +
        "</tr>";
    });

    $list.html(
      '<table class="nco-cart-table">' +
        "<thead><tr>" +
        "<th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th>" +
        "</tr></thead>" +
        "<tbody>" +
        rows +
        "</tbody>" +
        "</table>",
    );

    $total.text(fmt(Cart.total()));
    $totRow.removeClass("nco-hidden");
  }

  // =========================================================================
  // Boot
  // =========================================================================
  $(function () {
    initProductsList();
    initProductDetail();
    initCheckout();
  });
})(jQuery);
