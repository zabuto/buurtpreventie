/**
 * Initializeren van kalender
 *
 * @param string domId
 * @param integer year
 * @param integer month
 * @param mixed prev
 * @param mixed next
 * @param string ajaxUrl
 * @param string actionUrl
 * @param string redirUrl
 * @param boolean viewPast
 */
function buurtpreventieLoopschemaInit(domId, year, month, prev, next, ajaxUrl, actionUrl, redirUrl, viewPast) {
    if (viewPast) {
        var legend = [
            {type: "text", label: "= geen incidenten", badge: "00", classname: "badge-loopresultaat-ok"},
            {type: "text", label: "= incident(en)", badge: "00", classname: "badge-loopresultaat-nok"},
            {type: "text", label: "= geen resultaten", badge: "00", classname: "badge-loopresultaat-na"},
            {type: "spacer"},
            {type: "spacer"},
            {type: "text", label: "= zelf lopen", badge: "dag"},
            {type: "spacer"},
            {type: "block", label: "= 1 loper (onvoldoende deelnemers)", classname: "lopers-nok"},
            {type: "block", label: "= 2 of meer lopers (voldoende deelnemers)", classname: "lopers-ok"}
        ];
    } else {
        var legend = [
            {type: "text", label: "= zelf lopen", badge: "dag"},
            {type: "spacer"},
            {type: "block", label: "= 1 loper (onvoldoende deelnemers)", classname: "lopers-nok"},
            {type: "block", label: "= 2 of meer lopers (voldoende deelnemers)", classname: "lopers-ok"}
        ];
    }

    $("#" + domId).zabuto_calendar({
        year: year,
        month: month,
        language: "nl",
        show_previous: prev,
        show_next: next,
        today: true,
        ajax: { url: ajaxUrl, modal: true },
        legend: legend,
        action: function () {
            return buurtpreventieLoopschemaAanmelden(this.id, actionUrl, redirUrl, false);
        }
    });
}

/**
 * Aanmelden voor datum
 *
 * @param string dateDomId
 * @param string modalUrl
 * @param boolean fromModal
 * @return boolean
 */
function buurtpreventieLoopschemaAanmelden(dateDomId, modalUrl, redirUrl, fromModal) {
    if (fromModal) {
        $("#" + dateDomId + "_modal").modal("hide");
    }

    var date = $("#" + dateDomId).data("date");
    var hasEvent = $("#" + dateDomId).data("hasEvent");

    if (hasEvent && !fromModal) {
        return false;
    }

    var dateAr = date.split("-");
    var dateObj = new Date(dateAr[0], dateAr[1] - 1, dateAr[2], 0, 0, 0, 0);

    var todayObj = new Date();
    todayObj.setHours(0);
    todayObj.setMinutes(0);
    todayObj.setSeconds(0);
    todayObj.setMilliseconds(0);

    if (dateObj < todayObj) {
        return false;
    }

    modalUrl.toString();
    modalUrl = modalUrl.replace("placeholder", date);

    redirUrl.toString();
    redirUrl = redirUrl.replace("placeholderjaar", dateObj.getFullYear().toString());
    redirUrl = redirUrl.replace("placeholdermaand", (dateObj.getMonth() + 1).toString());

    $.get(modalUrl, function (data) {
        $("#dateEditModalLabel").html('Aanmelden voor ' + dateObj.toLocaleDateString());
        $("#dateEditModalBody").html(data);
        $("#dateEditModalSubmit").html('Aanmelden');
        $("#dateEditModalSubmit").removeClass('btn-primary');
        $("#dateEditModalSubmit").removeClass('btn-warning');
        $("#dateEditModalSubmit").addClass('btn-success');
        $('#dateEditModal').modal({keyboard: false});
    });

    $("#dateEditModalSubmit").off('click');
    $("#dateEditModalSubmit").click(function () {
        $('#buurtpreventieLoperCancelDateFormErrors').html('');
        var $form = $("#buurtpreventieLoperAddDateForm");
        $.post($form.attr('action'), $form.serialize(), function (response) {
            if (response.success) {
                window.location = redirUrl;
            } else {
                $('#buurtpreventieLoperAddDateFormErrors').empty();
                var $errorUl = $('<ul class="list-unstyled"></ul>');
                $.each(response.errors, function (index, value) {
                    $errorUl.append('<li>' + value + '</li>');
                });
                var $errorDiv = $('<div class="alert alert-danger"></div>');
                $errorDiv.append($errorUl);
                $('#buurtpreventieLoperAddDateFormErrors').append($errorDiv);
            }
        });
        return false;
    });

    return true;
}

/**
 * Afmelden voor datum
 *
 * @param dateDomId
 * @param recordId
 * @param modalUrl
 * @param fromModal
 * @returns {boolean}
 */
function buurtpreventieLoopschemaAfmelden(dateDomId, recordId, modalUrl, redirUrl, fromModal) {
    if (fromModal) {
        $("#" + dateDomId + "_modal").modal("hide");
    }
    var date = $("#" + dateDomId).data("date");

    var hasEvent = $("#" + dateDomId).data("hasEvent");
    if (!hasEvent && fromModal) {
        return false;
    }

    var dateAr = date.split("-");
    var dateObj = new Date(dateAr[0], dateAr[1] - 1, dateAr[2]);

    modalUrl.toString();
    modalUrl = modalUrl.replace("placeholder", recordId);

    redirUrl.toString();
    redirUrl = redirUrl.replace("placeholderjaar", dateObj.getFullYear().toString());
    redirUrl = redirUrl.replace("placeholdermaand", (dateObj.getMonth() + 1).toString());

    $.get(modalUrl, function (data) {
        $("#dateEditModalLabel").html('Afmelden voor ' + dateObj.toLocaleDateString());
        $("#dateEditModalBody").html(data);
        $("#dateEditModalSubmit").html('Afmelden');
        $("#dateEditModalSubmit").removeClass('btn-primary');
        $("#dateEditModalSubmit").removeClass('btn-success');
        $("#dateEditModalSubmit").addClass('btn-warning');
        $('#dateEditModal').modal({keyboard: false});
    });

    $("#dateEditModalSubmit").off('click');
    $("#dateEditModalSubmit").click(function () {
        $('#buurtpreventieLoperCancelDateFormErrors').html('');
        var $form = $("#buurtpreventieLoperCancelDateForm");
        $.post($form.attr('action'), $form.serialize(), function (response) {
            if (response.success) {
                window.location = redirUrl;
            } else {
                var $errorUl = $('<ul class="list-unstyled"</ul>');
                $.each(response.errors, function (index, value) {
                    $errorUl.append('<li>' + value + '</li>');
                });
                var $errorDiv = $('<div class="alert alert-danger"></div>');
                $errorDiv.append($errorUl);
                $('#buurtpreventieLoperCancelDateFormErrors').append($errorDiv);
            }
        });

        return false;
    });
}

/**
 * Resultaat voor datum
 *
 * @param dateDomId
 * @param recordId
 * @param modalUrl
 * @param fromModal
 * @returns {boolean}
 */
function buurtpreventieLoopschemaResultaat(dateDomId, recordId, modalUrl, redirUrl, fromModal) {
    if (fromModal) {
        $("#" + dateDomId + "_modal").modal("hide");
    }
    var date = $("#" + dateDomId).data("date");

    var hasEvent = $("#" + dateDomId).data("hasEvent");
    if (!hasEvent && fromModal) {
        return false;
    }

    var dateAr = date.split("-");
    var dateObj = new Date(dateAr[0], dateAr[1] - 1, dateAr[2]);

    modalUrl.toString();
    modalUrl = modalUrl.replace("placeholder", recordId);

    redirUrl.toString();
    redirUrl = redirUrl.replace("placeholderjaar", dateObj.getFullYear().toString());
    redirUrl = redirUrl.replace("placeholdermaand", (dateObj.getMonth() + 1).toString());

    $.get(modalUrl, function (data) {
        $("#dateEditModalLabel").html('Resultaat voor ' + dateObj.toLocaleDateString());
        $("#dateEditModalBody").html(data);
        $("#dateEditModalSubmit").html('Resultaat');
        $("#dateEditModalSubmit").removeClass('btn-primary');
        $("#dateEditModalSubmit").removeClass('btn-success');
        $("#dateEditModalSubmit").addClass('btn-warning');
        $('#dateEditModal').modal({keyboard: false});
    });

    $("#dateEditModalSubmit").off('click');
    $("#dateEditModalSubmit").click(function () {
        $('#buurtpreventieLoperResultDateFormErrors').html('');
        var $form = $("#buurtpreventieLoperResultDateForm");
        $.post($form.attr('action'), $form.serialize(), function (response) {
            if (response.success) {
                window.location = redirUrl;
            } else {
                var $errorUl = $('<ul class="list-unstyled"</ul>');
                $.each(response.errors, function (index, value) {
                    $errorUl.append('<li>' + value + '</li>');
                });
                var $errorDiv = $('<div class="alert alert-danger"></div>');
                $errorDiv.append($errorUl);
                $('#buurtpreventieLoperCancelDateFormErrors').append($errorDiv);
            }
        });

        return false;
    });
}
