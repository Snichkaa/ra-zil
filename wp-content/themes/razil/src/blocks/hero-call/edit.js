import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const { label, title, subtitle, phone, phoneLabel } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Содержимое блока', 'razil' ) }>
					<TextControl
						label={ __( 'Метка сверху', 'razil' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
						placeholder="Хабаровск · Круглосуточно"
					/>
					<TextControl
						label={ __( 'Заголовок', 'razil' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
						placeholder="Когда ночь, когда день"
					/>
					<TextControl
						label={ __( 'Подзаголовок', 'razil' ) }
						value={ subtitle }
						onChange={ ( value ) => setAttributes( { subtitle: value } ) }
						placeholder="Мы на связи 24/7 для семей города"
					/>
					<TextControl
						label={ __( 'Телефон', 'razil' ) }
						value={ phone }
						onChange={ ( value ) => setAttributes( { phone: value } ) }
						placeholder="+7 (4212) 60-52-90"
					/>
					<TextControl
						label={ __( 'Подпись под кнопкой', 'razil' ) }
						value={ phoneLabel }
						onChange={ ( value ) => setAttributes( { phoneLabel: value } ) }
						placeholder="Круглосуточно, без выходных"
					/>
				</PanelBody>
			</InspectorControls>

			<section { ...blockProps } className="wp-block-razil-hero-call">
				<div className="rz-hero-container">
					<p className="rz-hero-label">{ label }</p>
					<h1 className="rz-hero-title">{ title }</h1>
					<p className="rz-hero-subtitle">{ subtitle }</p>
					<div className="rz-hero-actions">
						<a href={ `tel:${ phone.replace( /[^\d+]/g, '' ) }` } className="rz-hero-phone-btn">
							☎ { phone }
						</a>
					</div>
					<p className="rz-hero-note">{ phoneLabel }</p>
				</div>
			</section>
		</>
	);
}
