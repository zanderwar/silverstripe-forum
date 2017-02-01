<?php
namespace SilverStripe\Forum\Form;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\View\Requirements;

/**
 * ForumRole_Validator
 *
 * This class is used to validate the new fields added by the
 * {@link ForumRole} decorator in the CMS backend.
 */
class ForumRoleValidator extends Extension
{
    /**
     * Client-side validation code
     *
     * @param string                   $js The javascript validation code
     *
     * @param Form $form
     *
     * @return string Returns the needed javascript code for client-side
     *                validation.
     */
    public function updateJavascript(&$js, Form &$form)
    {
        $formID            = $form->FormName();
        $passwordFieldName = $form->Fields()->fieldByName('Password')->ID();

        $passwordConfirmField = $form->Fields()->fieldByName('ConfirmPassword');
        if (!$passwordConfirmField) {
            return false;
        }

        $passwordConfirmFieldName = $passwordConfirmField->ID();

        $passwordCheck = <<<JS
Behaviour.register({
	"#$formID": {
		validatePasswordConfirmation: function() {
			var passEl = _CURRENT_FORM.elements['Password'];
			var confEl = _CURRENT_FORM.elements['ConfirmPassword'];

			if(passEl.value == confEl.value) {
			  clearErrorMessage(confEl.parentNode);
				return true;
			} else {
				validationError(confEl, "Passwords don't match.", "error");
				return false;
			}
		},
		initialize: function() {
			var passEl = $('$passwordFieldName');
			var confEl = $('$passwordConfirmFieldName');

			confEl.value = passEl.value;
		}
	}
});
JS;
        Requirements::customScript(
            $passwordCheck,
            'func_validatePasswordConfirmation'
        );

        $js .= "\$('$formID').validatePasswordConfirmation();";

        return $js;
    }
}