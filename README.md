<h1>AutoCustomerGroup - Norway Addon</h1>
<p>Magento 2 Module - Module to add Norway functionality to gwharton/module-autocustomergroup</p>

<h2>Norwegian VOEC Scheme</h2>
<p>This Scheme applies to shipments being sent from anywhere in the world to Consumers (Not B2B transactions) in Norway.</p>
<p>As of 1st April 2020, all sellers must (if their turnover to Norway exceeds 50,000 NOK) register for the Norway VOEC scheme, and collect Norwegian VAT for B2C transactions at the point of sale and remit to the Norwegian Government.</p>
<p>The module is capable of automatically assigning customers to the following categories.</p>
<ul>
    <li><b>Domestic</b> - For shipments within Norway, normal Norway VAT rules apply.</li>
    <li><b>Import B2B</b> - For shipments from outside of Norway to Norway and the buyer presents a Norway Business Number, then VAT should not be charged.</li>
    <li><b>Import Taxed</b> - For imports into Norway, where the value of each individual item is below or equal to 3,000 NOK, then Norway VAT should be charged.</li>
    <li><b>Import Untaxed</b> - For imports into Norway, where one or more individual items value is above 3,000 NOK, then VAT should NOT be charged and instead will be collected at the Norwegian border along with any duties due.</li>
</ul>
<p>You need to create the appropriate tax rules and customer groups, and assign these customer groups to the above categories within the module configuration. Please ensure you fully understand the tax rules of the country you are shipping to. The above should only be taken as a guide.</p>

<h2>Government Information</h2>
<p>Scheme information can be found <a href="https://www.skatteetaten.no/en/business-and-organisation/vat-and-duties/vat/foreign/e-commerce-voec/" target="_blank">on the Norwegian Tax Administration website here</a>.</p>

<h2>Order Value</h2>
<p>For the Norway VOEC Scheme, the following applies (This can be confirmed in
    <a href="https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/voec/voec-guidelines-mars-2024.pdf"
    target="_blank">Section 4</a>) :</p>
<ul>
    <li>When determining whether VAT should be charged (VAT Threshold) Shipping or Insurance Costs are not included in the value of the goods.</li>
    <li>When determining the amount of VAT to charge the Goods value does include Shipping and Insurance Costs.</li>
</ul>
<p>The <a href="https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/voec/voec-guidelines-mars-2024.pdf"
    target="_blank">Online Stores and Marketplace Guidelines</a> refers to bundling in Section 4 where it states that multiple items can be shipped in a single shipment,
    and it is the cost of each item that determines whether VAT should be charged on each item, so a consignment of 10 items, each sold for
    1,000 NOK, should still have VAT charged at the point of sale even though the total order value exceeds 3,000 NOK. The guidance recommends if an order contains a mix
    of above and below threshold items, that the order is split into separate orders. <b>Because of this, this module assumes that if
    any one single item in a shipment is above the threshold in value, then the entire order does not have VAT applied. Orders sent in
    this way will be processed at the Norwegian border with VAT being charged as appropriate. This is still in accordance with Section 4 of the above guidance.</b>
<p>More information on the scheme can be found on the
    <a href="https://www.skatteetaten.no/en/business-and-organisation/vat-and-duties/vat/foreign/e-commerce-voec/" target="_blank">Norwegian Tax Administration Website</a></p>

<h2>VAT Number Verification</h2>
<p>Norwegian Business Numbers are verified by a simple test that they are 9 digit numbers beginning with 8 or 9. No online lookups are performed.</p>

<h2>Pseudocode for group allocation</h2>
<p>Groups are allocated by evaluating the following rules in this order (If a rule matches, no further rules are evaluated).</p>
<ul>
<li>IF MerchantCountry IS Norway AND CustomerCountry IS Norway THEN Group IS Domestic.</li>
<li>IF MerchantCountry IS NOT Norway AND CustomerCountry IS Norway AND TaxIdentifier IS VALID THEN Group IS ImportB2B.</li>
<li>IF MerchantCountry IS NOT Norway AND CustomerCountry IS Norway AND OrderValue IS LESS THAN OR EQUAL TO Threshold THEN Group IS ImportTaxed.</li>
<li>IF MerchantCountry IS NOT Norway AND CustomerCountry IS Norway AND OrderValue IS MORE THAN Threshold THEN Group IS ImportUntaxed.</li>
<li>ELSE NO GROUP CHANGE</li>
</ul>

<h2>Configuration Options</h2>
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
<p>No specific setup is required to run the integration tests.</p>
