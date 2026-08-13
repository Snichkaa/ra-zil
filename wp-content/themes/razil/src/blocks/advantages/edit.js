import { useBlockProps } from '@wordpress/block-editor';
export default function Edit( { attributes } ) {
	return (
		<section { ...useBlockProps() } className="wp-block-razil-advantages">
			<div className="rz-advantages-list">
				{ attributes.items.map( ( item, i ) => (
					<div key={ i } className="rz-advantage">
						<p className="rz-advantage-accent">{ item.accent }</p>
						<p className="rz-advantage-text">{ item.text }</p>
					</div>
				) ) }
			</div>
		</section>
	);
}
