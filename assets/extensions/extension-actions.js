/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { checked } from '../icons/wordpress-icons';

/**
 * Extension actions component.
 *
 * @param {Object} props         Component props.
 * @param {Array}  props.actions Actions array containing objects with props for link or button.
 */
const ExtensionActions = ( { actions } ) => (
	<ul className="sensei-extensions__extension-actions">
		{ actions.map( ( { key, children, ...actionProps } ) => {
			const ActionComponent = actionProps.href ? 'a' : 'button';

			return (
				<li
					key={ key }
					className="sensei-extensions__extension-actions__item"
				>
					<ActionComponent
						className={
							actionProps.className || 'button button-primary'
						}
						{ ...actionProps }
					>
						{ children }
					</ActionComponent>
				</li>
			);
		} ) }
	</ul>
);

export default ExtensionActions;

/**
 * Get extension actions array.
 *
 * @param {Object} extension Extension object.
 *
 * @return {Array|null} Array of actions, or null if it's not a valid extension.
 */
export const getExtensionActions = ( extension ) => {
	if ( ! extension.product_slug ) {
		return null;
	}

	let buttonLabel = '';

	if ( extension.has_update ) {
		buttonLabel = __( 'Update', 'sensei-lms' );
	} else if ( extension.is_installed ) {
		buttonLabel = (
			<>
				<Icon
					className="sensei-extensions__extension-actions__button-icon"
					icon={ checked }
					size={ 14 }
				/>{ ' ' }
				{ __( 'Installed', 'sensei-lms' ) }
			</>
		);
	} else {
		buttonLabel = `${ __( 'Install', 'sensei-lms' ) } - ${
			extension.price !== '0'
				? extension.price
				: __( 'Free', 'sensei-lms' )
		}`;
	}

	let buttons = [
		{
			key: 'main-button',
			disabled: extension.is_installed && ! extension.has_update,
			children: buttonLabel,
		},
	];

	if ( extension.link ) {
		buttons = [
			...buttons,
			{
				key: 'more-details',
				href: extension.link,
				className: 'sensei-extensions__extension-actions__details-link',
				target: '_blank',
				rel: 'noreferrer external',
				children: __( 'More details', 'sensei-lms' ),
			},
		];
	}

	return buttons;
};