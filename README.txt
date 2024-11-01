=== TradeTracker Connect ===
Contributors: TradeTracker
Tags: affiliate marketing, order tracking, conversion tracking, TradeTracker, woocommerce
Requires at least: 5.5
Requires PHP: 7.4
Tested up to: 6.5
Stable tag: 2.2.10
License: GPLv3 or later

TradeTracker Connect enables Merchants using WooCommerce to start selling products or services using TradeTracker's Affiliate Marketing Network.

== Description ==
This extension implements the required conversion tracking and provides an easy-to-use product feed generator when the WooCommerce plugin is used. The plugin handles automated order adjustments and assessment for cancelled and returned orders.

Unique in their approach, affiliate programs on the [TradeTracker](https://tradetracker.com/) network can adopt Real Attribution, which leads to more promotion by publishers in every stage of the buying process – driving more revenue for brands. All publishers involved in the promotion of the product and brand get awarded their share of commission in accordance with the defined attribution model.

Full first party cycle tracking solution:
 - Installs conversion tracking automatically
 - Generates a product feed for promotion by product listing publishers

**First-party tracking**

The TradeTracker Connect extension allows full cycle first-party tracking on your own domain.

**Attribution models**

Determine the attribution model most suitable to the customer journey of consumers when they interact with your offers.

**Global sales**

Start your affiliate program on a global scale, benefiting from the transparent and robust TradeTracker platform and infrastructure.

### Features
* Turnkey solution, no XML or API knowledge required
* Conversion path and attribution tracking
* Tailored fit for the TradeTracker feed requirements
* Option to choose the unique identifiers (EAN / UPC or MPN)
* Easily add new extra attributes through the admin

#### Account & Pricing
The extension is free to use. To be able to use the TradeTracker Affiliate Network and be promoted by it's affiliates a small performance-based fee per transaction applies depending on your account type with TradeTracker. The details to connect the extension will be provided to you upon joining the TradeTracker network. [You can sign up here](https://tradetracker.com/advertisers/).

#### About TradeTracker
[TradeTracker](https://tradetracker.com) is an international affiliate network. Unique in their approach, TradeTracker provides [Real Attribution](https://tradetracker.com/real-attribution/), which leads to more promotion by publishers in every stage of the buying process – driving more revenue for brands. Enabling a network for both Advertisers and Publishers, TradeTracker hosts a platform offering real-time, understandable, and most of all transparent data, to strengthen their client’s ability to make the right performance marketing decisions.

#### About Affiliate Marketing
Affiliate marketing is often applauded for its ability to drive sales. Publishers are only paid when a visitor to their site is redirected to an advertiser’s site to then make a sale or takes a path to perform a desired action. This model ensures little to no risk taken by the advertiser, while publishers have the freedom to place creatives and utilise a variety of channels of their choice. Traditionally CPC (cost per click) and CPM (cost per mille) were the formative models of online advertising and was perfectly adequate during the web’s emergence. As online marketers became smarter though, they sought a more accountable approach. Affiliate marketing brought with it the ethos that ensured a benefit from only results and didn’t value interactions which did not lead to conversion.

== Frequently Asked Questions ==
= How can I start using this plugin for a TradeTracker affiliate campaign =
Starting with TradeTracker Affiliate Marketing consists of just two steps:
1. Sign up as an Advertiser on [the TradeTracker site](https://tradetracker.com/advertisers/).
2. Install this TradeTracker Connect Plugin and configure the settings

== Screenshots ==
1. First party tracking
2. Settings screen
3. Xml product feed generator / mapper
4. Multi-channel tracking
5. Affiliate marketing

#### TradeTracker Service Usage
This plugin uses following domains for sending tracking data
* ts.tradetracker.net (for Conversion tracking)
* tm.tradetracker.net (for Conversion tracking)

The data which is being is limited by:
* Parameters entered in module settings: Campaign ID, Tracking Group ID, Product Group ID;
* Parameters received from DirectLinking URL GET parameters: Campaign ID, Material ID, Affiliate ID, Redirect URL;
* Order data: ID, Number, Total amount, Currency, Voucher code and ordered products data: ID, Name, Category, Brand, Price, SKU, EAN codes;

[Terms of Use](https://tradetracker.com/terms-of-use/)
[Privacy and Data Policy](https://tradetracker.com/privacy-policy/)

#### Install TradeTracker Connect from within WordPress

* Visit the plugins page within your dashboard and select ‘Add New’;
* Search for ‘TradeTracker Connect’;
* Activate TradeTracker Connect from your Plugins page;
* Go to ‘after activation’ below.

#### INSTALL TradeTracker Connect MANUALLY

* Upload the ‘tradetracker-connect’ folder to the /wp-content/plugins/ directory;
* Activate the TradeTracker Connect plugin through the ‘Plugins’ menu in WordPress;
* Go to ‘after activation’ below.

#### after activation
Go to the WordPress Admin and click the TradeTracker menu option and enter the details as you have received from your TradeTracker Account Manager.

== Changelog ==
= 2.2.10 =
- Added temporary file for product feed generation

= 2.2.9 =
- Added time limit to the product feed

= 2.2.8 =
- Show last generated time of product feed

= 2.2.7 =
- Fixed product feed generation
- Fixed order tracking at checkout

= 2.2.6 =
- Added return type check for product list endpoint

= 2.2.5 =
- Removed legacy WooCommerce API requirement

= 2.2.4 =
- First release of TradeTracker Connect plugin to Wordpress plugin directory

== Upgrade Notice ==
= 2.2.3 =
- Upgrade to this version to (automatically) get updates through the WorPress plugin directory going forward.