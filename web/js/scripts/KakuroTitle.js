import React from 'react';
import { Grid, Input } from 'semantic-ui-react';
import _ from 'lodash';
import PropTypes from 'prop-types';

class KakuroTitle extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            title: props.title,
        };
    }

    render() {
        if (this.props.editable) {
            return (
                <div className="row kak-title">
                    <Input defaultValue={this.props.title} onChange={this.props.onChange} />
                </div>
            );
        }

        return (
            <div className="row kak-title">
                {this.props.title}
            </div>
        )
    }
}

KakuroTitle.propTypes = {
    title: PropTypes.node,
    editable: PropTypes.bool,
    onChange: PropTypes.func,
}

KakuroTitle.defaultProps = {
    title: 'NoName',
    editable: false,
    onChange: () => {},
}

export default KakuroTitle;
