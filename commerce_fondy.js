(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.commerceFondyForm = {
    attach: function (context) {
        fondy("#fondy-checkout-container", JSON.parse(drupalSettings.commerceFondy));
    }
  }

})(jQuery, Drupal, drupalSettings);
