$(function () {
    const config = window.autoreplyFormConfig || {};
    const form = $("#autoreply-form");
    const typeSelect = $("#autoreply-type");
    const triggerEventInputs = $(".trigger-event-radio");
    const previewPanel = $("#autoreply-preview-panel");
    const simulateResult = $("#simulate-result");
    const aiSummaryPanel = $("#autoreply-ai-summary");

    function syncAiBotSummary() {
        const selectedDeviceId = String($('select[name="device_id"]').val() || "");
        const bots = config.aiBots || [];

        $("#autoreply-ai-bot-id option").each(function () {
            const option = $(this);
            const botDeviceId = String(option.data("device-id") || "");
            if (!botDeviceId || !selectedDeviceId) {
                option.prop("hidden", false);
                return;
            }

            option.prop("hidden", botDeviceId !== selectedDeviceId && option.val() !== "");
        });

        const currentOption = $("#autoreply-ai-bot-id option:selected");
        if (currentOption.length && currentOption.prop("hidden")) {
            $("#autoreply-ai-bot-id").val("");
        }

        const selectedBotId = String($("#autoreply-ai-bot-id").val() || "");
        const selectedBot = bots.find(function (bot) {
            return String(bot.id) === selectedBotId;
        });

        if (!selectedBot) {
            aiSummaryPanel.html('<div class="text-muted">Pilih AI bot profile untuk melihat ringkasan cara kerja bot.</div>');
            return;
        }

        aiSummaryPanel.html(
            '<div class="fw-semibold mb-1">' + selectedBot.name + '</div>' +
            '<div class="small text-muted mb-2">' +
                (selectedBot.engine_type || "AI") + " - " +
                (selectedBot.model || "default") + " - " +
                (selectedBot.thinking_mode || "Balanced") +
            "</div>" +
            '<div class="small">' + (selectedBot.persona || selectedBot.response_style || "Bot AI akan menjawab mengikuti prompt dan konteks chat.") + "</div>"
        );
    }

    function rememberFieldNames() {
        $(".autoreply-type-panel").each(function () {
            $(this)
                .find("input, textarea, select")
                .each(function () {
                    const field = $(this);
                    if (!field.attr("data-field-name") && field.attr("name")) {
                        field.attr("data-field-name", field.attr("name"));
                    }
                });
        });
    }

    function syncTypePanels() {
        const selectedType = typeSelect.val();

        $(".autoreply-type-panel").each(function () {
            const panel = $(this);
            const isActive = panel.data("type-panel") === selectedType;
            panel.toggleClass("d-none", !isActive);

            panel.find("input, textarea, select").each(function () {
                const field = $(this);
                const originalName = field.attr("data-field-name");
                if (!originalName) {
                    return;
                }

                if (isActive) {
                    field.attr("name", originalName);
                } else {
                    field.removeAttr("name");
                }
            });
        });
    }

    function syncScheduleFields() {
        const isScheduled = $('input[name="schedule_mode"]:checked').val() === "scheduled";
        const scheduleFields = $("#schedule-fields");
        scheduleFields.toggleClass("d-none", !isScheduled);

        scheduleFields.find('input[name="schedule_days[]"], input[name="schedule_start"], input[name="schedule_end"]').each(function () {
            $(this).prop("disabled", !isScheduled);
        });
    }

    function syncTriggerEventFields() {
        const triggerEvent = $('input[name="trigger_event"]:checked').val();
        const isFirstChat = triggerEvent === "first_chat";

        $(".keyword-trigger-fields").toggleClass("d-none", isFirstChat);
        $(".first-chat-trigger-note").toggleClass("d-none", !isFirstChat);

        $('input[name="keyword"], select[name="type_keyword"]').each(function () {
            $(this).prop("disabled", isFirstChat);
        });

        $("#simulate-first-chat").prop("checked", isFirstChat);
    }

    function bindRepeatableActions() {
        $(document).on("click", ".add-repeatable", function () {
            const target = $($(this).data("target"));
            const name = $(this).data("name");
            target.append(
                '<div class="input-group mb-2 repeatable-row">' +
                    '<input type="text" name="' + name + '[]" class="form-control">' +
                    '<button type="button" class="btn btn-outline-danger remove-repeatable">Hapus</button>' +
                "</div>"
            );
        });

        $(document).on("click", ".remove-repeatable", function () {
            const container = $(this).closest(".repeatable-group");
            if (container.find(".repeatable-row").length <= 1) {
                $(this).siblings("input").val("");
                return;
            }

            $(this).closest(".repeatable-row").remove();
        });
    }

    function bindFileManager() {
        $(".lfm-picker").each(function () {
            const button = $(this);
            const inputId = button.data("input");
            button.filemanager("file", {
                prefix: config.fileManagerPrefix,
            });

            if (!button.attr("id")) {
                button.attr("id", inputId + "-trigger");
            }
        });
    }

    function runSimulation() {
        const payload = form.serializeArray();
        payload.push({ name: "sample_message", value: $("#simulate-message").val() });
        payload.push({ name: "sample_context", value: $("#simulate-context").val() });
        payload.push({ name: "sample_is_first_chat", value: $("#simulate-first-chat").is(":checked") ? 1 : 0 });
        payload.push({ name: "sample_registered_contact", value: $("#simulate-registered-contact").is(":checked") ? 1 : 0 });

        simulateResult
            .removeClass("d-none bg-light-danger bg-light-info bg-light-success")
            .addClass("bg-light-info")
            .html("Menjalankan simulasi...");

        $.ajax({
            url: config.simulateUrl,
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: $.param(payload),
            success: function (result) {
                const list = (result.reasons || [])
                    .map(function (reason) {
                        return "<li>" + reason + "</li>";
                    })
                    .join("");

                if (result.matched) {
                    simulateResult
                        .removeClass("bg-light-info bg-light-danger")
                        .addClass(result.source === "draft" ? "bg-light-success" : "bg-light-warning")
                        .html(
                            '<div class="fw-semibold mb-1">Rule terpilih: ' +
                                (result.rule ? result.rule.name : "-") +
                                '</div><ul class="mb-0">' + list + "</ul>"
                        );
                    previewPanel.html(result.preview_html);
                } else {
                    simulateResult
                        .removeClass("bg-light-info bg-light-success")
                        .addClass("bg-light-danger")
                        .html('<div class="fw-semibold mb-1">Tidak ada rule yang lolos.</div><ul class="mb-0">' + list + "</ul>");
                    previewPanel.html(result.preview_html);
                }
            },
            error: function (xhr) {
                let message = "Simulasi gagal dijalankan.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                simulateResult
                    .removeClass("bg-light-info bg-light-success")
                    .addClass("bg-light-danger")
                    .html(message);
            },
        });
    }

    rememberFieldNames();
    bindRepeatableActions();
    bindFileManager();
    syncTypePanels();
    syncScheduleFields();
    syncTriggerEventFields();
    syncAiBotSummary();

    typeSelect.on("change", syncTypePanels);
    triggerEventInputs.on("change", syncTriggerEventFields);
    $(".schedule-mode-radio").on("change", syncScheduleFields);
    $('select[name="device_id"], #autoreply-ai-bot-id').on("change", syncAiBotSummary);
    $("#simulate-autoreply-btn").on("click", runSimulation);
});
