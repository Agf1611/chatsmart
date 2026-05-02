$(function () {
    const previewModalElement = document.getElementById("autoreplyPreviewModal");
    const previewModal = previewModalElement ? new bootstrap.Modal(previewModalElement) : null;

    $(document).on("click", ".autoreply-preview-btn", function () {
        const id = $(this).data("id");
        const url = $(this).data("preview-url");

        $(".autoreply-preview-body").html('<div class="text-center py-5 text-muted">Memuat preview...</div>');

        $.ajax({
            url: url,
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: {
                id: id,
                table: "autoreplies",
                column: "reply",
            },
            dataType: "html",
            success: function (result) {
                $(".autoreply-preview-body").html(result);
                if (previewModal) {
                    previewModal.show();
                }
            },
            error: function () {
                $(".autoreply-preview-body").html('<div class="alert alert-danger mb-0">Preview gagal dimuat.</div>');
                if (previewModal) {
                    previewModal.show();
                }
            },
        });
    });

    $(document).on("change", ".autoreply-status-toggle", function () {
        const toggle = $(this);
        const url = toggle.data("url");
        const label = toggle.closest(".form-check").find(".form-check-label");
        const checked = toggle.is(":checked");

        $.ajax({
            url: url,
            type: "POST",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            data: {
                active: checked ? 1 : 0,
            },
            success: function (result) {
                label.text(result.status);
                toastr.success(result.msg);
            },
            error: function () {
                toggle.prop("checked", !checked);
                toastr.error("Status rule gagal diperbarui.");
            },
        });
    });
});
