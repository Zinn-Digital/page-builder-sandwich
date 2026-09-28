/**
 * Every free design-system block's editor module, in registry order. The editor registers this
 * list (src/blocks/index.js) and so does the test bar, so both see exactly the same blocks.
 * ⛔ Keep the markers: wp/bin/pbs-new-block.php inserts above them. The vitest leg holds this
 * list to includes/blocks/class-registry.php in both directions.
 */
// pbs-blocks-imports:start
import * as alert from './alert';
import * as section from './section';
import * as container from './container';
import * as tabs from './tabs';
import * as tab from './tab';
import * as accordion from './accordion';
import * as accordionItem from './accordion-item';
import * as modal from './modal';
import * as offCanvas from './off-canvas';
import * as tooltip from './tooltip';
import * as tableOfContents from './table-of-contents';
import * as readingProgress from './reading-progress';
import * as starRating from './star-rating';
import * as progressBar from './progress-bar';
import * as iconList from './icon-list';
import * as infoBox from './info-box';
import * as featureList from './feature-list';
import * as steps from './steps';
import * as checklist from './checklist';
import * as badge from './badge';
import * as dualHeading from './dual-heading';
import * as glossary from './glossary';
import * as socialIcons from './social-icons';
import * as testimonial from './testimonial';
import * as callToAction from './call-to-action';
import * as comparisonTable from './comparison-table';
import * as map from './map';
import * as booking from './booking';
import * as qrCode from './qr-code';
import * as code from './code';
import * as relatedPosts from './related-posts';
import * as postMeta from './post-meta';
import * as sitemap from './sitemap';
import * as userProfile from './user-profile';
import * as protectedContent from './protected';
import * as contactButtons from './contact-buttons';
import * as whatsapp from './whatsapp';
import * as form from './form';
// pbs-blocks-imports:end

export const BLOCKS = [
	// pbs-blocks-list:start
	alert,
	section,
	container,
	tabs,
	tab,
	accordion,
	accordionItem,
	modal,
	offCanvas,
	tooltip,
	tableOfContents,
	readingProgress,
	starRating,
	progressBar,
	iconList,
	infoBox,
	featureList,
	steps,
	checklist,
	badge,
	dualHeading,
	glossary,
	socialIcons,
	testimonial,
	callToAction,
	comparisonTable,
	map,
	booking,
	qrCode,
	code,
	relatedPosts,
	postMeta,
	sitemap,
	userProfile,
	protectedContent,
	contactButtons,
	whatsapp,
	form,
	// pbs-blocks-list:end
];
