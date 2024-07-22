(function ($){

    typenow = (typeof typenow !== 'undefined') ? typenow : '';

    $(document).ready(function (){
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        if(urlParams.has('export')) {
            let formData = {
                action: 'stm_cie_export_selected',
                export: urlParams.get('export'),
                type: typenow,
                nonce: ms_lms_nonce,
            };
            $.ajax({
                url: stm_lms_ajaxurl,
                dataType: 'json',
                method: 'post',
                context: this,
                data: formData,
                complete: function (data) {
                    data = data['responseJSON'];
                    if(typeof data.exportData !== 'undefined') {
                        $("<a />", {
                            "download": "data.json",
                            "href" : "data:application/json," + encodeURIComponent(JSON.stringify(data.exportData))
                        }).appendTo("body")
                            .click(function() {
                                $(this).remove()
                            })[0].click()
                    }
                }
            });
        }
    });

})(jQuery);