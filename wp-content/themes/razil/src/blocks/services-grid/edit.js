import { useBlockProps } from '@wordpress/block-editor';
export default function Edit( { attributes } ) {
	const { services } = attributes;
	return (
		<section { ...useBlockProps() } className="wp-block-razil-services-grid">
			<div className="rz-services-grid">
				{ services.map( ( s, i ) => (
					<div key={ i } className="rz-service-card">
						<h3 className="rz-service-title">{ s.title }</h3>
						<p className="rz-service-desc">{ s.description }</p>
						<p className="rz-service-price">от { s.price } ₽</p>
					</div>
				) ) }
			</div>
		</section>
	);
}
