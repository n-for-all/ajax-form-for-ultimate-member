jQuery(document).ready(function() {
    if (typeof(um_ajax) != "undefined") {
        jQuery(um_ajax.forms).on("click", "input[type=submit]", function(event) {
            event.preventDefault();
            var form = jQuery(event.delegateTarget);
            var data = form.find("form").serializeArray(); // convert form to array
            data.push({
                name: "action",
                value: "um_submit_form"
            });
            form.addClass("um-loading");
            jQuery.post(um_ajax.endpoint, data, function(resp) {
                if (form.find(".um-notice").length == 0) {
                    form.prepend('<p style="display:none" class="um-notice"></p>');
                }
                var notice = form.find(".um-notice");
                if (resp.status != "success") {
                    if(resp.status == "submit"){
                        form.find("form").submit();
                        return true;
                    }
                    if(resp.nonce){
                        form.find('#_wpnonce').val(resp.nonce);
                    }
                    notice.removeClass("success").removeClass("err").addClass("err");
                } else {
                    notice.removeClass("err").removeClass("success").addClass("success");
                    if(resp.redirect){
                        window.location.href = resp.redirect;
                        return true;
                    }
                }
                notice.html(resp.message);
                notice.show();
            }, "json").always(function(){
                form.removeClass("um-loading");
            });
        });
    }
});
