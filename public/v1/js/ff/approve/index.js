
$(function () {
    "use strict";

    if ($('#inputDateRange').length > 0) {

        $('#inputDateRange').daterangepicker(
            {
                locale: {
                    format: 'YYYY-MM-DD',
                    firstDay: 1
                },
                format: 'YYYY-MM-DD',
                minDate: minDate,
                drops: 'down'
            }
        );

        // set approve user from cookie, if any:
        // if (!(readCookie('approve-user') === null)) {
        //     $('select[name="approve_user"]').val(readCookie('approve-user'));
        // }

        // set category from cookie
        // if ((readCookie('category') !== null)) {
        //     $('select[name="category"]').val(readCookie('approve-category'));
        // }

        // set status from cookie
        // if ((readCookie('statu') !== null)) {
        //     $('select[name="statu"]').val(readCookie('approve-statu'));
        // }

        // set date from cookie
        // var startStr = readCookie('approve-start');
        // var endStr = readCookie('approve-end');
        // if (startStr !== null && endStr !== null && startStr.length === 8 && endStr.length === 8) {
        //     var startDate = moment(startStr, "YYYY-MM-DD");
        //     var endDate = moment(endStr, "YYYY-MM-DD");
        //     var datePicker = $('#inputDateRange').data('daterangepicker');
        //     datePicker.setStartDate(startDate);
        //     datePicker.setEndDate(endDate);
        // }
    }

    // $('.date-select').on('click', preSelectDate);
    // $('#approve-form').on('submit', catchSubmit);
    // $('select[name="approve_user"]').on('change', getExpenses);
    $('#inputApproveUser').on('change', uiChanged);
    $('#inputCategory').on('change', uiChanged);
    $('#inputStatu').on('change', uiChanged);
    $('#inputExpense').on('change', uiChanged);
    $('#inputDateRange').on('change', uiChanged);
    $('.statusAction').on('click', statusChanged);

    // getExpenses();
});

function getExpenses() {
    "use strict";
    var approveUser = $('#inputApproveUser').val();
    var expenseBody = $('#inputExpense');
    
    // var box = $('#extra-options-box');
    // box.find('.overlay').show();

    $.getJSON('approve/expenses/' + approveUser, function (datas) {
        console.log('[SPIDER] [Expense] Setting Inner html');
        var html = "<option label='All' value='0'>All</option>";
        datas.forEach(function (data) {
            var optionHtml = "<option label='" + data.name + "' value='" + data.id + "'>" + data.name + "</option>";
            html += optionHtml;
            console.log('[SPIDER] [Expenses JSON DATA]', optionHtml);
        });
        console.log(html);
        expenseBody.html(html);
        // expenseBody.innerHTML = html;
        // console.log('[SPIDER] [Expenses JSON DATA]', datas);
        // setOptionalFromCookies();
        // box.find('.overlay').hide();
    }).fail(function () {
        console.log('[SPIDER] [ERROR] While Getting Expenses user no : ', approveUser);
        // boxBody.addClass('error');
        // box.find('.overlay').hide();
    });
}

function uiChanged() { 
    // console.log('[SPIDER] UI Changed');
    // saveCookies();
    console.log('redirecting...');
    updateTable();
}

function statusChanged(event) {
    tranid = event.target.children[0].getAttribute('value');
    statuid = event.target.children[1].getAttribute('value');
    console.log(tranid, statuid);
    $.getJSON('approve/actions/' + tranid + '/' + statuid, function (data) {
        $('#status_'+tranid).html(data);
    }).fail(function () {
        console.log('Update Status Failed');        
    });
}


function updateTable() {
    var approveUser = $('#inputApproveUser').val();
    var category = $('#inputCategory').val();
    var statu = $('#inputStatu').val();
    var expense = $('#inputExpense').val();
    var picker = $('#inputDateRange').data('daterangepicker');
    var stDate = moment(picker.startDate).format("YYYYMMDD");
    var edDate = moment(picker.endDate).format("YYYYMMDD");
    window.location.replace('approve/approves/' + approveUser + '/' + category + '/' + statu + '/' + expense + '/' + stDate + '/' + edDate);

    // $.getJSON('approve/approves/' + approveUser + '/' + category + '/' + statu + '/' + expense + '/' + stDate + '/' + edDate, function (html) {
    //     // console.log(html);
    //     $('#tableApproves').html(html);
    // }).fail(function () {
    //     console.log('[SPIDER] [ERROR] While Getting Approves user no : ', approveUser);
    // });
}
function saveCookies() {
    "use strict";
    // date, processed:
    var picker = $('#inputDateRange').data('daterangepicker');

    // all account ids:
    var approveUser = $('#inputApproveUser').val();
    var category = $('#inputCategory').val();
    var statu = $('#inputStatu').val();
    var expense = $('#inputExpense').val();

    // remember all
    // set cookie to remember choices.
    createCookie('approve-user', approveUser, 365);
    createCookie('approve-category', category, 365);
    createCookie('approve-statu', statu, 365);
    createCookie('approve-expense', expense, 365);
    createCookie('approve-start', moment(picker.startDate).format("YYYYMMDD"), 365);
    createCookie('approve-end', moment(picker.endDate).format("YYYYMMDD"), 365);

    return true;
}