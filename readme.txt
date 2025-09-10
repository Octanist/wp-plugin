=== Octanist ===
Contributors: octanist
Tags: tracking, forms, leads, conversions, analytics
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress forms to the Octanist platform for powerful, seamless offline conversion tracking. See which campaigns deliver real customers.

== Description ==

### What is Octanist?

Octanist is a powerful platform designed to bridge the gap between your online marketing efforts and your offline sales. While other analytics tools show you clicks and form submissions, Octanist helps you track what happens *after* the lead comes in. By connecting your website's leads to your sales data, you can finally get a clear, accurate picture of your marketing ROI and identify which campaigns are delivering real, paying customers.

This plugin is the official connector for WordPress, making it incredibly simple to send all your form submissions directly into your Octanist account.

### How The Plugin Works

The Octanist plugin is designed to be a lightweight yet powerful "set it and forget it" tool. Once configured, it automatically detects and captures submissions from the most popular form plugins in WordPress.

*   **Automatic Form Detection:** The plugin automatically finds forms from plugins like Contact Form 7, Elementor Forms, WPForms, Forminator, and more.
*   **Intelligent Field Mapping:** Our intuitive settings panel allows you to map your various form fields (e.g., "your-name", "full_name") to the standard Octanist properties ("Name"). If a form has multiple fields that map to the same property (like First Name and Last Name), the plugin intelligently combines them.
*   **Flexible Submission Handling:** We understand that not all form plugins are the same. That's why we've included both an AJAX mode (for modern forms that don't reload the page) and a Standard mode (for traditional forms), ensuring maximum compatibility.
*   **Debug Mode:** For easy troubleshooting, you can enable a debug mode that logs detailed information to your browser's console, helping you solve any integration issues in seconds.

### Data Privacy & Compliance

Connecting your website to a third-party service requires careful attention to data privacy. Octanist and this plugin are designed with this in mind.

*   **Octanist Account Required:** To use this plugin, you must have an active account with Octanist.com.
*   **Data Processing Agreement (DPA):** As the owner of the website, you are the data controller. By using Octanist, you should have a DPA in place. Please contact Octanist support to arrange this.
*   **Consent & Local Regulations (GDPR, etc.):** It is your responsibility to comply with all local data privacy regulations. This includes, but is not limited to, obtaining proper consent from users before collecting their data and ensuring your privacy policy is up to date. The use of a consent management platform (CMP) to handle cookie and tracking consent is highly recommended.

== Installation ==

1.  Upload the `octanist` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Settings > Octanist** to configure the plugin.
4.  Enter your **Octanist ID** (found in your Octanist dashboard under Integrations).
5.  Configure your **Field Mappings** to match the fields in your forms.
6.  Select the appropriate **Form Submission Mode** for your site.
7.  Save your changes.

== Frequently Asked Questions ==

= My form submissions aren't showing up in Octanist. What should I do? =

The first step is to enable **Debug Mode** in the plugin settings (under the "Advanced" card). Open your browser's developer console (usually by pressing F12) and submit a test form. The debug logs will tell you:
*   Which forms the plugin found on the page.
*   The data it captured from the form.
*   Whether it successfully sent the data to Octanist.

If the form submission seems to break or the page reloads unexpectedly, try switching the **Form Submission Mode** from "AJAX" to "Standard" (or vice-versa).

= Do I need an Octanist account to use this plugin? =

Yes. This plugin is a connector and requires an active Octanist account to function.

= How does the advanced field mapping work? =

Different forms use different names for the same type of field (e.g., `email`, `your-email`). The mapping tool lets you account for all these variations. For each standard property (Name, Email, Phone), you can add multiple field names that the plugin should look for. If a form contains multiple fields that map to the same property (like "First Name" and "Last Name"), their values will be intelligently combined with a pipe symbol ( | ).

== Changelog ==

= 2.0.0 =
*   **MAJOR REFACTOR:** Overhauled the entire plugin to use the WordPress Settings API for improved security and stability.
*   **NEW:** Added a professional, ShadCN-inspired UI for the settings page.
*   **NEW:** Implemented an advanced, dynamic UI for field mappings. No more comma-separated values!
*   **NEW:** Added a "Debug Mode" for easy troubleshooting.
*   **IMPROVEMENT:** Form submission logic now intelligently handles both AJAX and standard forms.
*   **IMPROVEMENT:** Values from multiple fields mapped to the same property (e.g., First Name, Last Name) are now intelligently combined.
*   **IMPROVEMENT:** Plugin menu is now correctly located under the main "Settings" menu.

= 1.0.0 =
*   Initial release.
*   Basic form tracking for popular plugins.
*   Basic field mapping.
