$(function ($) {
    $(document).on("click", ".account-select", function () {
        let element = $(this);
        let accountnumber = element.val();
        if (element.hasClass("is-customer")) {
            bootbox.dialog({
                title: _t("warning"),
                message: `<form id='activate-account-form' method='post'>` +
                    _t("account_not_activated") + `
                    <label class='font-weight-bold text-primary'>` + _t("email") + `</label>
                    <input type='email' name='email' class='form-control' placeholder='`+ _t("email") + `'/>

                    <label class='font-weight-bold text-primary'>` + _t("password") + `</label>
                    <input type='password' name='password' class='form-control' placeholder='`+ _t("password") + `'/>

                    <label class='font-weight-bold text-primary'>` + _t("password_again") + `</label>
                    <input type='password' name='password_again' class='form-control' placeholder='`+ _t("password_again") + `'/>
                    
                    <input type='text' name='accountnumber' value='${accountnumber}' class='d-none'>
                </form>`,
                centerVertical: true,
                buttons: {
                    cancel: {
                        label: _t("cancel"),
                        className: 'btn-danger',
                    },
                    confirm: {
                        label: _t("activate_account"),
                        className: 'btn-primary',
                        callback: function () {
                            let data = {};
                            $("#activate-account-form").serializeArray().map(function (x) { data[x.name] = x.value; });
                            $.ajax({
                                url: `${root}/api/activateAccountRequest`,
                                method: "post",
                                data: data,
                                dataType: "json",
                                success: function (response) {
                                    bootbox.alert({
                                        title: _t("info"),
                                        message: response.data,
                                        closeButton: false,
                                        callback: function () {
                                            location.assign(root);
                                        }
                                    });
                                }
                            });
                            return false;
                        }
                    },
                }
            });
        } else {
            bootbox.dialog({
                title: _t("warning"),
                message: `<form id='new-linked-account-form' method='post'>` +
                    _t("please_enter_new_email", [root + "/forgetpassword"]) + `
                    <label class='font-weight-bold text-primary'>` + _t("email") + `</label>
                    <input type='email' name='email' class='form-control' placeholder='`+ _t("email") + `'/>

                    <label class='font-weight-bold text-primary'>` + _t("name") + `</label>
                    <input type='text' name='name' class='form-control' placeholder='`+ _t("name") + `'/>

                    <label class='font-weight-bold text-primary'>` + _t("surname") + `</label>
                    <input type='text' name='surname' class='form-control' placeholder='`+ _t("surname") + `'/>

                    <label class='font-weight-bold text-primary'>` + _t("password") + `</label>
                    <input type='password' name='password' class='form-control' placeholder='`+ _t("password") + `'/>

                    <label class='font-weight-bold text-primary'>` + _t("password_again") + `</label>
                    <input type='password' name='password_again' class='form-control' placeholder='`+ _t("password_again") + `'/>
                    
                    <input type='text' name='accountnumber' value='${accountnumber}' class='d-none'>
                </form>`,
                centerVertical: true,
                buttons: {
                    cancel: {
                        label: _t("cancel"),
                        className: 'btn-danger',
                    },
                    confirm: {
                        label: _t("ok"),
                        className: 'btn-primary',
                        callback: function () {
                            let data = {};
                            $("#new-linked-account-form").serializeArray().map(function (x) { data[x.name] = x.value; });
                            $.ajax({
                                url: `${root}/api/saveEmailChangeRequest`,
                                method: "post",
                                data: data,
                                dataType: "json",
                                success: function (response) {
                                    bootbox.alert({
                                        title: _t("info"),
                                        message: response.data,
                                        closeButton: false,
                                        callback: function () {
                                            location.assign(root);
                                        }
                                    });
                                }
                            });
                            return false;
                        }
                    },
                }
            });
        }
    }).on("click", ".where-account-no", function (e) {
        e.preventDefault();
        bootbox.alert({
            message: `<img class="img-fluid" src="${root}/assets/invoicesample.jpg"/>`
        })
    })
})