/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "../../";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 36);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./csl_theme/src/forms/register-form/register-form.js":
/*!************************************************************!*\
  !*** ./csl_theme/src/forms/register-form/register-form.js ***!
  \************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

$(function($){$(document).on("click",".account-select",function(){var element=$(this);var accountnumber=element.val();if(element.hasClass("is-customer")){bootbox.dialog({title:_t("warning"),message:"<form id='activate-account-form' method='post'>"+_t("account_not_activated")+"\n                    <label class='font-weight-bold text-primary'>"+_t("email")+"</label>\n                    <input type='email' name='email' class='form-control' placeholder='"+_t("email")+"'/>\n\n                    <label class='font-weight-bold text-primary'>"+_t("password")+"</label>\n                    <input type='password' name='password' class='form-control' placeholder='"+_t("password")+"'/>\n\n                    <label class='font-weight-bold text-primary'>"+_t("password_again")+"</label>\n                    <input type='password' name='password_again' class='form-control' placeholder='"+_t("password_again")+"'/>\n                    \n                    <input type='text' name='accountnumber' value='".concat(accountnumber,"' class='d-none'>\n                </form>"),centerVertical:true,buttons:{cancel:{label:_t("cancel"),className:'btn-danger'},confirm:{label:_t("activate_account"),className:'btn-primary',callback:function callback(){var data={};$("#activate-account-form").serializeArray().map(function(x){data[x.name]=x.value;});$.ajax({url:"".concat(root,"/api/activateAccountRequest"),method:"post",data:data,dataType:"json",success:function success(response){bootbox.alert({title:_t("info"),message:response.data,closeButton:false,callback:function callback(){location.assign(root);}});}});return false;}}}});}else{bootbox.dialog({title:_t("warning"),message:"<form id='new-linked-account-form' method='post'>"+_t("please_enter_new_email",[root+"/forgetpassword"])+"\n                    <label class='font-weight-bold text-primary'>"+_t("email")+"</label>\n                    <input type='email' name='email' class='form-control' placeholder='"+_t("email")+"'/>\n\n                    <label class='font-weight-bold text-primary'>"+_t("name")+"</label>\n                    <input type='text' name='name' class='form-control' placeholder='"+_t("name")+"'/>\n\n                    <label class='font-weight-bold text-primary'>"+_t("surname")+"</label>\n                    <input type='text' name='surname' class='form-control' placeholder='"+_t("surname")+"'/>\n\n                    <label class='font-weight-bold text-primary'>"+_t("password")+"</label>\n                    <input type='password' name='password' class='form-control' placeholder='"+_t("password")+"'/>\n\n                    <label class='font-weight-bold text-primary'>"+_t("password_again")+"</label>\n                    <input type='password' name='password_again' class='form-control' placeholder='"+_t("password_again")+"'/>\n                    \n                    <input type='text' name='accountnumber' value='".concat(accountnumber,"' class='d-none'>\n                </form>"),centerVertical:true,buttons:{cancel:{label:_t("cancel"),className:'btn-danger'},confirm:{label:_t("ok"),className:'btn-primary',callback:function callback(){var data={};$("#new-linked-account-form").serializeArray().map(function(x){data[x.name]=x.value;});$.ajax({url:"".concat(root,"/api/saveEmailChangeRequest"),method:"post",data:data,dataType:"json",success:function success(response){bootbox.alert({title:_t("info"),message:response.data,closeButton:false,callback:function callback(){location.assign(root);}});}});return false;}}}});}}).on("click",".where-account-no",function(e){e.preventDefault();bootbox.alert({message:"<img class=\"img-fluid\" src=\"".concat(root,"/assets/invoicesample.jpg\"/>")});});});

/***/ }),

/***/ 36:
/*!******************************************************************!*\
  !*** multi ./csl_theme/src/forms/register-form/register-form.js ***!
  \******************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! ./csl_theme/src/forms/register-form/register-form.js */"./csl_theme/src/forms/register-form/register-form.js");


/***/ })

/******/ });
//# sourceMappingURL=register-form.js.map