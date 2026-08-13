import { useBlockProps } from '@wordpress/block-editor';
import './editor.scss';

export default function Edit( { attributes } ) {
	const { steps } = attributes;
	const blockProps = useBlockProps();

	return (
		<section { ...blockProps } className="wp-block-razil-steps">
			<div className="rz-steps-container">
				{ steps.map( ( step, index ) => (
					<details key={ index } className="rz-step" open={ index === 0 }>
						<summary className="rz-step-summary">
							<span className="rz-step-number">{ step.number }</span>
							<span className="rz-step-title">{ step.title }</span>
						</summary>
						<div className="rz-step-content">
							<p>{ step.description }</p>
						</div>
					</details>
				) ) }
			</div>
		</section>
	);
}
