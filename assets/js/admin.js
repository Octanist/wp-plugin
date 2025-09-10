jQuery(document).ready(function ($) {
  // Handle adding new mapping fields
  $(".add-mapping-field").on("click", function () {
    const field = $(this).data("field");
    const wrapper = $("#mapping-wrapper-" + field);
    const newField =
      '<div><input type="text" name="octanist_settings[field_mappings][' +
      field +
      '][]" value="" class="regular-text" /> <button type="button" class="button remove-mapping-field">-</button></div>';
    wrapper.append(newField);
  });

  // Handle removing mapping fields
  $("body").on("click", ".remove-mapping-field", function () {
    const wrapper = $(this).closest('div[id^="mapping-wrapper-"]');
    // Don't remove the last field, just clear it
    if (wrapper.children().length > 1) {
      $(this).parent().remove();
    } else {
      $(this).prev("input").val("");
    }
  });
});
