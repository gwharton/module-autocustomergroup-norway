<h1>AutoCustomerGroup - Norway Addon</h1>
<p>Magento 2 Module - Module to add Norway functionality to gwharton/module-autocustomergroup</p>
<h2>Norwegian VOEC Scheme</h2>
<h3>Configuration Options</h3>
<ul>
<li><b>Enabled</b> - Enable/Disable this Tax Scheme.</li>
<li><b>Tax Identifier Field - Customer Prompt</b> - Displayed under the Tax Identifier field at checkout when a shipping country supported by this module is selected. Use this to include information to the user about why to include their Tax Identifier.</li>
<li><b>VOEC Registration Number</b> - The Norway VOEC Registration Number for the Merchant. This is not currently used by the module, however supplementary functions in AutoCustomerGroup may use this, for example displaying on invoices etc.</li>
<li><b>Import VAT Threshold</b> - If any single item within the order is valued above the VAT threshold then no VAT should be charged.</li>
<li><b>Use Magento Exchange Rate</b> - To convert from NOK Threshold to Store Currency Threshold, should we use the Magento Exchange Rate, or our own.</li>
<li><b>Exchange Rate</b> - The exchange rate to use to convert from NOK Threshold to Store Currency Threshold.</li>
<li><b>Customer Group - Domestic</b> - Merchant Country is within Norway, Item is being shipped to Norway.</li>
<li><b>Customer Group - Import B2B</b> - Merchant Country is not within Norway, Item is being shipped to Norway, Norwegian Business Number passed validation by module.</li>
<li><b>Customer Group - Import Taxed</b> - Merchant Country is not within Norway, Item is being shipped to Norway, All items valued at or below the Import VAT Threshold.</li>
<li><b>Customer Group - Import Untaxed</b> - Merchant Country is not within Norway, Item is being shipped to Norway, One or more items in the order is valued above the Import VAT Threshold.</li>
</ul>
<h2>Integration Tests</h2>
<p>No credentials are needed to run the integration tests.</p>
