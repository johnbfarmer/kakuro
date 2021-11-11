import React from 'react';
import { Grid, Button, Search } from 'semantic-ui-react';
import _ from 'lodash';
import PropTypes from 'prop-types';

class SearchControl extends React.Component {
    constructor(props) {
        super(props);

        this.handleResultSelect = this.handleResultSelect.bind(this);
        this.handleSearchChange = this.handleSearchChange.bind(this);

        this.initialState = {
            loading: false,
            results: [],
        };

        this.state = {
            loading: false,
            results: [],
            value: props.selected,
        };
    }

    handleResultSelect(e, { result }) { this.setState({ value: result.title, loading: false, results: [] }, () => {this.props.onChange(result.value)}) }

    resultRenderer(res) {
        return ( 
            <div className="kak-srch-result" >
                {res.title}
            </div>
        );
    }

    handleSearchChange(e, { value }) {
        this.setState({ loading: true, value })

        setTimeout(() => {
            if (this.state.value.length < 1) return this.setState(this.initialState)
                const re = new RegExp(_.escapeRegExp(this.state.value), 'i')
                const isMatch = (result) => re.test(result.title)
                this.setState({
                    loading: false,
                    results: _.filter(this.props.grids, isMatch),
            })
        }, 300)
    }

    render() {
        return (
            <div className="row">
                <Search 
                    loading={this.state.loading}
                    onResultSelect={this.handleResultSelect}
                    onSearchChange={_.debounce(this.handleSearchChange, 500, {
                        leading: true,
                    })}
                    results={this.state.results}
                    value={this.state.value}
                    resultRenderer={this.resultRenderer}
                    showNoResults={false}
                    icon='search'
                />
            </div>
        )
    }
}

SearchControl.propTypes = {
    grids: PropTypes.array,
    selected: PropTypes.node,
    onChange: PropTypes.func,
}

SearchControl.defaultProps = {
    grids: [],
    selected: 0,
    onChange: () => {},
}

export default SearchControl;
