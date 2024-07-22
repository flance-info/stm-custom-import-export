"use strict";
(function($) {

    $(document).on('ready',function(){

        const stm_cie_import_app = new Vue({
            el: '#stm-cie-import-app',
            data: function data() {
                return {
                    loading: false,
                    import_file: null,
                    filename: '',
                    skip_for_names: false,
                    message: '',
                    created: {},
                    skipped: {},
                    detected: {},
                    show_info: false,
                    message_status: 'success'
                };
            },
            methods: {
                changeInput: function changeInput(){
                    let vm = this;
                    let import_file = vm.$refs.import_file.files;
                    let file = (typeof import_file[0] !== 'undefined' ) ? import_file[0] : false;
                    vm.filename = file.name;
                },
                sendRequest: function sendRequest(event){
                    let vm = this;
                    let import_file = vm.$refs.import_file.files;

                    let formData = new FormData;
                    let file = (typeof import_file[0] !== 'undefined' ) ? import_file[0] : false;

                    formData.append('import_file', file);
                    formData.append('skip_for_names', vm.skip_for_names);
                    formData.append('_wpnonce', ms_lms_nonce);
                    vm.loading = true;
                    vm.show_info = false;
                    vm.message = '';

                    vm.$http.post(stm_lms_ajaxurl + '?action=stm_cie_import', formData).then(function(response){
                        if(typeof response.body.message !== 'undefined') {
                            vm.message = response.body.message;
                            if(response.body.success){
                                vm.message_status = 'success';
                            } else {
                                vm.message_status = 'danger';
                            }
                            vm.loading = false;
                        }

                        if(typeof response.body.success !== 'undefined') {
                            setTimeout(function (){
                                vm.checkRequest();
                            }, 2000);
                        }

                    });

                },
                checkRequest: function checkRequest(){
                    let vm = this;
                    let formData = new FormData;
                    formData.append('_wpnonce', ms_lms_nonce);

                    vm.show_info = true;

                    vm.$http.post(stm_lms_ajaxurl + '?action=stm_cie_check_import', formData).then(function(response){

                        if(typeof response.body.message !== 'undefined') {
                            vm.message = response.body.message;
                            if(response.body.success){
                                vm.message_status = 'success';
                            } else {
                                vm.message_status = 'danger';
                            }
                        }
                        console.log(response);


                        if(typeof response.body.show_info !== 'undefined') {
                            vm.show_info = true;
                        }
                        if(typeof response.body.skipped !== 'undefined') {
                            vm.skipped = response.body.skipped;
                        }
                        if(typeof response.body.created !== 'undefined') {
                            vm.created = response.body.created;
                        }
                        if(typeof response.body.detected !== 'undefined') {
                            vm.detected = response.body.detected;
                        }

                        if(typeof response.body.ended === 'undefined') {
                            setTimeout(function (){
                                vm.checkRequest();
                            }, 3000);
                        } else {
                            vm.loading = false;
                        }
                    });
                }
            }
        });

    });
})(jQuery);
