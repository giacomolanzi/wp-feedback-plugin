(function ($) {
  "use strict";

  let currentDetailId = null;

  // Prefill form if user is logged in.
  $(document).on("ready", function () {
    if (window.WP_FEEDBACK && WP_FEEDBACK.prefill) {
      $("#wpf_first_name").val(WP_FEEDBACK.prefill.first_name || "");
      $("#wpf_last_name").val(WP_FEEDBACK.prefill.last_name || "");
      $("#wpf_email").val(WP_FEEDBACK.prefill.email || "");
    }
  });

  // Submit via AJAX
  $(document).on("submit", "#wpf-form", function (e) {
    e.preventDefault();

    // Rimuovi eventuali errori
    $("#wpf-response").empty();

    const $form = $(this);
    const data = $form.serializeArray();
    data.push({ name: "action", value: "wp_feedback_submit" });
    data.push({ name: "_wpnonce", value: WP_FEEDBACK.nonceForm });

    $form.find('button[type="submit"]').prop("disabled", true);

    $.post(WP_FEEDBACK.ajaxUrl, data)
      .done(function (resp) {
        if (resp && resp.success) {
          $("#wpf-form").replaceWith('<p class="wpf-thanks">' + WP_FEEDBACK.i18n.thanks + "</p>");
        } else {
          $("#wpf-response").text(WP_FEEDBACK.i18n.error);
        }
      })
      .fail(function (xhr) {
        let msg = WP_FEEDBACK.i18n.error;

        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.errors) {
          const errs = xhr.responseJSON.data.errors;
          const arr = Array.isArray(errs) ? errs : Object.values(errs);

          msg =
            '<ul class="wpf-errors">' +
            arr
              .map(function (e) {
                return "<li>" + e + "</li>";
              })
              .join("") +
            "</ul>";
        }

        $("#wpf-response").html(msg);
      })

      .always(function () {
        $form.find('button[type="submit"]').prop("disabled", false);
      });
  });

  // ADMIN LIST (loaded only on click)
  function renderList(container, rows) {
    if (!rows || !rows.length) {
      container.html("<p>" + WP_FEEDBACK.i18n.noEntries + "</p>");
      return;
    }
    const html = rows
      .map(function (r) {
        const safeName = $("<div/>")
          .text((r.first_name || "") + " " + (r.last_name || ""))
          .html();
        const safeEmail = $("<div/>")
          .text(r.email || "")
          .html();
        const safeSubj = $("<div/>")
          .text(r.subject || "")
          .html();
        const safeDate = $("<div/>")
          .text(r.created_at || "")
          .html();

        return '<button class="wpf-item" data-id="' + r.id + '" type="button">' + '<span class="wpf-col wpf-name">' + safeName + "</span>" + '<span class="wpf-col wpf-email">' + safeEmail + "</span>" + '<span class="wpf-col wpf-subject">' + safeSubj + "</span>" + '<span class="wpf-col wpf-date">' + safeDate + "</span>" + "</button>";
      })
      .join("");
    container.html('<div class="wpf-head">' + '<span class="wpf-col wpf-name"><strong>Nome Cognome</strong></span>' + '<span class="wpf-col wpf-email"><strong>Email</strong></span>' + '<span class="wpf-col wpf-subject"><strong>Oggetto</strong></span>' + '<span class="wpf-col wpf-date"><strong>Data</strong></span>' + "</div>" + html);
  }

  function renderPagination(container, total, page, perPage) {
    const pages = Math.max(1, Math.ceil(total / perPage));
    if (pages <= 1) {
      container.empty();
      return;
    }
    let html = "";
    if (page > 1) {
      html += '<button class="wpf-page" data-p="' + (page - 1) + '">&laquo; Prev</button>';
    }
    for (let p = 1; p <= pages; p++) {
      html += '<button class="wpf-page' + (p === page ? " is-active" : "") + '" data-p="' + p + '">' + p + "</button>";
    }
    if (page < pages) {
      html += '<button class="wpf-page" data-p="' + (page + 1) + '">Next &raquo;</button>';
    }
    container.html(html);
  }

  function loadPage(page) {
    const data = {
      action: "wp_feedback_list",
      _wpnonce: WP_FEEDBACK.nonceList,
      page: page || 1,
      per_page: 10,
    };
    $.post(WP_FEEDBACK.ajaxUrl, data)
      .done(function (resp) {
        if (resp && resp.success) {
          renderList($("#wpf-list"), resp.data.rows);
          renderPagination($("#wpf-pagination"), resp.data.total, resp.data.page, resp.data.per_page);
          $("#wpf-detail").empty(); // reset dettaglio quando cambio pagina
        } else {
          $("#wpf-list").html("<p>" + WP_FEEDBACK.i18n.error + "</p>");
        }
      })
      .fail(function () {
        $("#wpf-list").html("<p>" + WP_FEEDBACK.i18n.error + "</p>");
      });
  }

  function loadDetail(id) {
    const data = {
      action: "wp_feedback_get",
      _wpnonce: WP_FEEDBACK.nonceList,
      id: id,
    };
    $.post(WP_FEEDBACK.ajaxUrl, data)
      .done(function (resp) {
        if (resp && resp.success) {
          const r = resp.data.row || {};
          const escape = function (s) {
            return $("<div/>")
              .text(s || "")
              .html();
          };

          const html =
            "" +
            '<div class="wpf-card">' +
            '<button type="button" class="wpf-close">Chiudi ×</button>' +
            "<div><strong>Nome:</strong> " +
            escape(r.first_name) +
            "</div>" +
            "<div><strong>Cognome:</strong> " +
            escape(r.last_name) +
            "</div>" +
            "<div><strong>Email:</strong> " +
            escape(r.email) +
            "</div>" +
            "<div><strong>Oggetto:</strong> " +
            escape(r.subject) +
            "</div>" +
            '<div><strong>Messaggio:</strong><br><pre class="wpf-msg">' +
            escape(r.message) +
            "</pre></div>" +
            "<div><strong>Data:</strong> " +
            escape(r.created_at) +
            "</div>" +
            "</div>";

          $("#wpf-detail").html(html);
          currentDetailId = id; // ⬅️ assegna l’id aperto
        } else {
          $("#wpf-detail").html("<p>" + WP_FEEDBACK.i18n.error + "</p>");
        }
      })

      .fail(function () {
        $("#wpf-detail").html("<p>" + WP_FEEDBACK.i18n.error + "</p>");
      });
  }

  // Click per caricare (no preload)
  $(document).on("click", "#wpf-load", function () {
    loadPage(1);
  });

  // Paginazione AJAX
  $(document).on("click", ".wpf-page", function () {
    const p = parseInt($(this).data("p"), 10) || 1;
    loadPage(p);
  });

  // Dettaglio al click voce lista toggle
  $(document).on("click", ".wpf-item", function () {
    const id = parseInt($(this).data("id"), 10);
    if (!id) return;

    // Se stai cliccando la stessa riga già aperta -> chiudi
    if (currentDetailId === id) {
      $("#wpf-detail").empty();
      currentDetailId = null;
      $(".wpf-item.is-active").removeClass("is-active");
      return;
    }

    // Altrimenti apri il nuovo dettaglio
    $(".wpf-item.is-active").removeClass("is-active");
    $(this).addClass("is-active");
    loadDetail(id);
  });
  // Chiudi dal bottone nella scheda
  $(document).on("click", "#wpf-detail .wpf-close", function () {
    $("#wpf-detail").empty();
    currentDetailId = null;
    $(".wpf-item.is-active").removeClass("is-active");
  });
})(jQuery);
