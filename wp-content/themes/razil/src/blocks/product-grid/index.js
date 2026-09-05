import { registerBlockType } from "@wordpress/blocks";
import metadata from "./block.json";
import "./style.scss";

registerBlockType( metadata.name, {
	...metadata,
	render: metadata.render,
} );