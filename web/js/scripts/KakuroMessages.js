import React from 'react';
import { Grid, Input } from 'semantic-ui-react';
import _ from 'lodash';
import PropTypes from 'prop-types';

class KakuroMessages extends React.Component {
    render() {
        let msg = this.props.messages.map((m,i) => {
            return (
                <div className="" key={i}>
                    { m }
                </div>
            );
        });
        return (
            <div className="kak-message-box">
                {msg}
            </div>
        )
    }
}

KakuroMessages.propTypes = {
    messages: PropTypes.array,
}

KakuroMessages.defaultProps = {
    messages: '',
}

export default KakuroMessages;
