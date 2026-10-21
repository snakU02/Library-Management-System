/* ==========================================================
   lms.js — Custom JavaScript for Library Management System
   ========================================================== */

$(function () {

    // ---- Auto-dismiss flash alerts after 5 seconds ----
    setTimeout(function () {
        $('.alert:not(.alert-danger)').fadeOut('slow');
    }, 5000);

    // ---- Confirm all delete/deactivate forms ----
    $('[data-confirm]').on('click', function (e) {
        if (!confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });

    // ---- DataTable default initialization ----
    // Called individually per page with custom config

    // ---- Tooltip initialization ----
    $('[title]').tooltip({ placement: 'top', trigger: 'hover' });

    // ---- Active sidebar link highlight ----
    var path = window.location.pathname.split('/').pop();
    $('.nav-link').each(function () {
        var href = $(this).attr('href');
        if (href && href.indexOf(path) !== -1) {
            $(this).closest('.nav-item').addClass('active');
        }
    });

    // ---- Book search: auto-submit on 3+ characters (catalogue) ----
    var searchTimeout;
    $('#liveSearch').on('keyup', function () {
        clearTimeout(searchTimeout);
        var val = $(this).val();
        if (val.length >= 3 || val.length === 0) {
            searchTimeout = setTimeout(function () {
                $('#liveSearchForm').submit();
            }, 600);
        }
    });

    // ---- Penalty calculator: real-time total ----
    function updatePenaltyTotal() {
        var overdueDays = parseInt($('#penaltyOverdueDays').val()) || 0;
        var dailyRate   = parseFloat($('#penaltyDailyRate').val()) || 0;
        var damageFee   = parseFloat($('#penaltyDamageFee').val()) || 0;
        var total       = (overdueDays * dailyRate) + damageFee;
        $('#penaltyTotal').text('₱' + total.toFixed(2));
        $('#penaltyTotalInput').val(total.toFixed(2));
    }

    $('#penaltyOverdueDays, #penaltyDailyRate, #penaltyDamageFee').on('input', updatePenaltyTotal);

    // ---- Mobile: collapse sidebar on nav-link click ----
    if (window.innerWidth < 768) {
        $('.sidebar .nav-link').on('click', function () {
            $('body').toggleClass('sidebar-toggled');
            $('.sidebar').toggleClass('toggled');
        });
    }

    // ---- Print button ----
    $('#printBtn').on('click', function () {
        window.print();
    });

});

// ---- Global: format PHP peso ----
function formatPeso(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ---- Global: show loading spinner on form submit ----
$('form').on('submit', function () {
    var btn = $(this).find('[type="submit"]');
    if (btn.length) {
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Processing...');
    }
});
